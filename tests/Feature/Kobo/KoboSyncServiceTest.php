<?php

namespace Tests\Feature\Kobo;

use App\Models\KoboSubmission;
use App\Models\Vorgang;
use App\Services\Kobo\KoboSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The manual fallback for when the webhook is not delivering.
 */
class KoboSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBaseline();
        Storage::fake('local');
    }

    private function fakeApi(array $submissions): void
    {
        Http::fake([
            '*/api/v2/assets/*/data/*' => Http::response([
                'count' => count($submissions),
                'results' => $submissions,
            ]),
            '*' => Http::response(['ok' => true]),
        ]);
    }

    public function test_it_imports_submissions_that_never_arrived_by_webhook(): void
    {
        $this->fakeApi([
            $this->koboFixture('kobo-submission-point'),
            $this->koboFixture('kobo-submission-polygon'),
        ]);

        $result = app(KoboSyncService::class)->sync(queue: false);

        $this->assertFalse($result->failed());
        $this->assertSame(2, $result->imported);
        $this->assertSame(0, $result->skipped);
        $this->assertSame(2, KoboSubmission::count());
        $this->assertSame(2, Vorgang::count());
    }

    public function test_running_it_twice_imports_nothing_new(): void
    {
        $this->fakeApi([$this->koboFixture('kobo-submission-point')]);

        $service = app(KoboSyncService::class);

        $service->sync(queue: false);
        $second = $service->sync(queue: false);

        $this->assertSame(0, $second->imported);
        $this->assertSame(1, $second->skipped);
        $this->assertSame(1, Vorgang::count());
    }

    public function test_it_does_not_duplicate_a_case_that_arrived_by_webhook(): void
    {
        $payload = $this->koboFixture('kobo-submission-polygon');

        // The fake must be in place first: the queue runs synchronously in
        // tests, so the follow-up geocoding job fires inside the request.
        $this->fakeApi([$payload]);

        // Already delivered by the webhook.
        $this->postJson('/api/webhooks/kobo', $payload, ['X-Kobo-Secret' => 'test-webhook-secret'])
            ->assertAccepted();

        $result = app(KoboSyncService::class)->sync(queue: false);

        $this->assertSame(0, $result->imported);
        $this->assertSame(1, $result->skipped);
        $this->assertSame(1, Vorgang::count());
    }

    public function test_it_adopts_a_case_imported_from_the_old_monitor(): void
    {
        $payload = $this->koboFixture('kobo-submission-polygon');

        // Same report, already present with the office's own work on it.
        $existing = Vorgang::factory()->create([
            'kobo_id' => $payload['_id'],
            'melde_id' => null,
            'local_nr' => 'N-000001',
            'measure' => 'Stadt melden und Zustand prüfen',
        ]);

        $this->fakeApi([$payload]);

        app(KoboSyncService::class)->sync(queue: false);

        $this->assertSame(1, Vorgang::count(), 'Es darf kein zweiter Vorgang entstehen.');

        $existing->refresh();

        $this->assertSame('N-000001', $existing->local_nr);
        $this->assertSame('Stadt melden und Zustand prüfen', $existing->measure);
        $this->assertNotNull($existing->kobo_submission_id);
    }

    public function test_it_reports_an_api_failure_instead_of_throwing(): void
    {
        Http::fake(['*' => Http::response(['detail' => 'Invalid token.'], 401)]);

        $result = app(KoboSyncService::class)->sync();

        $this->assertTrue($result->failed());
        $this->assertSame(0, $result->imported);
        $this->assertStringContainsString('Abruf fehlgeschlagen', $result->summary());
    }

    public function test_the_status_reports_how_many_submissions_are_missing(): void
    {
        $this->fakeApi([
            $this->koboFixture('kobo-submission-point'),
            $this->koboFixture('kobo-submission-polygon'),
        ]);

        $status = app(KoboSyncService::class)->status();

        $this->assertSame(2, $status['remote']);
        $this->assertSame(0, $status['local']);
        $this->assertSame(2, $status['missing']);
    }
}
