<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a privately stored attachment.
 *
 * Reached through a signed, expiring URL and additionally checked against the
 * viewer's policy on the owning record - a leaked link must not become a
 * permanent public URL to someone's property.
 */
class AttachmentController extends Controller
{
    public function show(Request $request, Attachment $attachment): StreamedResponse
    {
        $owner = $attachment->attachable;

        abort_if($owner === null, 404);

        $this->authorize('view', $owner);

        $disk = Storage::disk($attachment->disk);

        abort_unless($disk->exists($attachment->path), 404);

        return $disk->response(
            $attachment->path,
            $attachment->original_filename,
            ['Content-Type' => $attachment->mime ?: 'application/octet-stream'],
            $request->boolean('download') ? 'attachment' : 'inline',
        );
    }
}
