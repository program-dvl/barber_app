<?php

namespace App\Http\Controllers;

use App\Domain\ClientRecords\Services\ClientAttachmentService;
use App\Support\Audit\AuditWriter;
use Symfony\Component\HttpFoundation\Response;

class ClientAttachmentDownloadController extends Controller
{
    public function __invoke(string $token, ClientAttachmentService $attachments, AuditWriter $audit): Response
    {
        $attachment = $attachments->resolve($token);
        $contents = $attachments->contents($attachment);
        $audit->write('client.attachment_accessed', $attachment->business, null, $attachment, null, [], [
            'attachment_public_id' => $attachment->public_id, 'purpose' => 'download',
        ], [], 'client_records');

        return response($contents, 200, [
            'Content-Type' => $attachment->mime_type,
            'Content-Disposition' => 'attachment; filename="'.addcslashes(basename($attachment->original_name), '"\\').'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
