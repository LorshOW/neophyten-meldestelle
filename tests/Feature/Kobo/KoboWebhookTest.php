<?php

namespace Tests\Feature\Kobo;

use App\Models\KoboSubmission;
use App\Models\Vorgang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KoboWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBaseline();

        Storage::fake('local');

        // The queue runs synchronously in tests, so the follow-up jobs fire
        // inside the request. Neither may touch the network.
        Http::fake([
            'eu.kobotoolbox.org/*' => Http::response($this->fakeJpeg(), 200, [
                'Content-Type' => 'image/jpeg',
            ]),
            'nominatim.openstreetmap.org/*' => Http::response([
                'address' => [
                    'postcode' => '09419',
                    'town' => 'Thum',
                    'county' => 'Erzgebirgskreis',
                ],
            ]),
        ]);
    }

    private function sendWebhook(array $payload, ?string $secret = 'test-webhook-secret')
    {
        $headers = $secret === null ? [] : ['X-Kobo-Secret' => $secret];

        return $this->postJson('/api/webhooks/kobo', $payload, $headers);
    }

    public function test_it_rejects_a_request_without_the_shared_secret(): void
    {
        $this->sendWebhook($this->koboFixture('kobo-submission-point'), secret: null)
            ->assertUnauthorized();

        $this->assertDatabaseCount('kobo_submissions', 0);
    }

    public function test_it_rejects_a_request_with_a_wrong_secret(): void
    {
        $this->sendWebhook($this->koboFixture('kobo-submission-point'), secret: 'falsch')
            ->assertUnauthorized();

        $this->assertDatabaseCount('kobo_submissions', 0);
    }

    public function test_it_stores_the_payload_verbatim_before_processing(): void
    {
        Queue::fake();

        $payload = $this->koboFixture('kobo-submission-point');

        $this->sendWebhook($payload)->assertAccepted();

        $submission = KoboSubmission::sole();

        $this->assertSame($payload, $submission->payload);
        $this->assertSame(815399208, $submission->kobo_id);
        $this->assertSame('aUTdQWQfjDBbnYqBtqWWUP', $submission->asset_uid);
        $this->assertSame('fdd4a270-e530-46b2-b872-ef061f56cd33', $submission->kobo_uuid);
    }

    public function test_it_never_stores_the_shared_secret_with_the_payload(): void
    {
        Queue::fake();

        $this->sendWebhook($this->koboFixture('kobo-submission-point'))->assertAccepted();

        $headers = KoboSubmission::sole()->headers ?? [];

        $this->assertArrayNotHasKey('x-kobo-secret', array_change_key_case($headers));
    }

    public function test_a_replayed_submission_creates_exactly_one_vorgang(): void
    {
        $payload = $this->koboFixture('kobo-submission-point');

        $this->sendWebhook($payload)->assertAccepted();
        $this->sendWebhook($payload)->assertOk()->assertJson(['duplicate' => true]);
        $this->sendWebhook($payload)->assertOk()->assertJson(['duplicate' => true]);

        $this->assertDatabaseCount('kobo_submissions', 1);
        $this->assertDatabaseCount('vorgaenge', 1);
    }

    public function test_it_creates_a_vorgang_in_the_initial_status(): void
    {
        $this->sendWebhook($this->koboFixture('kobo-submission-point'))->assertAccepted();

        $vorgang = Vorgang::sole();

        $this->assertSame('neu', $vorgang->status->key);
        $this->assertSame('NEO-MTFKZC5V-G6W06', $vorgang->melde_id);
        $this->assertSame('N-000001', $vorgang->local_nr);
        $this->assertSame(KoboSubmission::STATUS_PROCESSED, $vorgang->koboSubmission->processing_status);
    }

    public function test_a_submission_adopts_a_case_imported_from_the_old_monitor(): void
    {
        $payload = $this->koboFixture('kobo-submission-polygon');

        // The same report, already present from meldestelle:import-stand.
        $existing = \App\Models\Vorgang::factory()->create([
            'melde_id' => $payload['melde_id'],
            'local_nr' => 'N-000001',
            'editor_note' => 'Bereits vom Innendienst geprüft.',
            'kobo_submission_id' => null,
        ]);

        $this->sendWebhook($payload)->assertAccepted();

        // No second case, and the office's own work survives.
        $this->assertDatabaseCount('vorgaenge', 1);

        $existing->refresh();

        $this->assertSame('N-000001', $existing->local_nr);
        $this->assertSame('Bereits vom Innendienst geprüft.', $existing->editor_note);
        $this->assertNotNull($existing->kobo_submission_id);
    }

    public function test_it_rejects_an_empty_payload(): void
    {
        $this->sendWebhook([])->assertStatus(400);
    }
}
