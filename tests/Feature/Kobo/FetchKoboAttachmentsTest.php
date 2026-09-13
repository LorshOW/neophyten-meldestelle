<?php

namespace Tests\Feature\Kobo;

use App\Jobs\FetchKoboAttachments;
use App\Models\Attachment;
use App\Models\KoboSubmission;
use App\Models\Vorgang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FetchKoboAttachmentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBaseline();
        Storage::fake('local');
    }

    private function submissionWithAttachment(): KoboSubmission
    {
        $vorgang = Vorgang::factory()->create();

        $submission = KoboSubmission::create([
            'asset_uid' => 'aUTdQWQfjDBbnYqBtqWWUP',
            'kobo_id' => 815399208,
            'payload' => $this->koboFixture('kobo-submission-point'),
            'received_at' => now(),
            'vorgang_id' => $vorgang->getKey(),
        ]);

        return $submission;
    }

    public function test_it_downloads_with_the_kobo_token_and_stores_privately(): void
    {
        Http::fake([
            'eu.kobotoolbox.org/*' => Http::response($this->fakeJpeg(), 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $submission = $this->submissionWithAttachment();

        FetchKoboAttachments::dispatchSync($submission->getKey());

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Token test-api-token'));

        $attachment = Attachment::sole();

        $this->assertSame(Attachment::SOURCE_KOBO, $attachment->source);
        $this->assertSame(555001, $attachment->kobo_attachment_id);
        $this->assertSame('image/jpeg', $attachment->mime);
        $this->assertNotNull($attachment->checksum);

        Storage::disk('local')->assertExists($attachment->path);

        // Must never land somewhere publicly readable.
        $this->assertStringStartsWith('meldungen/', $attachment->path);
        $this->assertSame('local', $attachment->disk);
    }

    public function test_it_does_not_download_the_same_attachment_twice(): void
    {
        Http::fake([
            'eu.kobotoolbox.org/*' => Http::response($this->fakeJpeg(), 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $submission = $this->submissionWithAttachment();

        FetchKoboAttachments::dispatchSync($submission->getKey());
        FetchKoboAttachments::dispatchSync($submission->getKey());

        $this->assertSame(1, Attachment::count());
    }

    public function test_it_sanitises_the_filename_from_the_submission(): void
    {
        Http::fake([
            'eu.kobotoolbox.org/*' => Http::response($this->fakeJpeg(), 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $vorgang = Vorgang::factory()->create();
        $payload = $this->koboFixture('kobo-submission-point');
        $payload['_attachments'][0]['filename'] = '../../../etc/passwd';

        $submission = KoboSubmission::create([
            'asset_uid' => 'aUTdQWQfjDBbnYqBtqWWUP',
            'kobo_id' => 999,
            'payload' => $payload,
            'received_at' => now(),
            'vorgang_id' => $vorgang->getKey(),
        ]);

        FetchKoboAttachments::dispatchSync($submission->getKey());

        $attachment = Attachment::sole();

        $this->assertStringNotContainsString('..', $attachment->path);
        $this->assertStringNotContainsString('/etc/', $attachment->path);
    }

    public function test_it_skips_quietly_when_no_credentials_are_configured(): void
    {
        config(['kobo.api_token' => null]);

        $submission = $this->submissionWithAttachment();

        FetchKoboAttachments::dispatchSync($submission->getKey());

        // No exception, no request, no attachment - just a logged warning.
        $this->assertSame(0, Attachment::count());
    }
}
