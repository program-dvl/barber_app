<?php

namespace App\Http\Controllers\Shop\ClientRecords;

use App\Domain\ClientRecords\Models\Client;
use App\Domain\ClientRecords\Models\ClientAttachment;
use App\Domain\ClientRecords\Services\ClientAttachmentService;
use App\Domain\PlatformAccess\Models\Business;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientAttachmentController extends Controller
{
    public function store(Request $request, Business $business, Client $client, ClientAttachmentService $attachments, TenantContext $context): RedirectResponse
    {
        abort_unless($client->business_id === $business->id, 404);
        $this->authorize('viewAttachments', $client);
        $data = $request->validate([
            'attachment' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf'],
            'kind' => ['required', Rule::in(['file', 'before', 'after', 'profile_photo'])],
            'visibility' => ['required', Rule::in(['standard', 'sensitive'])],
        ]);
        if ($data['visibility'] === 'sensitive') {
            abort_unless($request->user()->can('viewSensitive', $client), 403);
        }
        $file = $request->file('attachment');
        $attachments->store($client, $file->getContent(), $file->getClientOriginalName(), $data['kind'], $data['visibility'], null, $context->membership()?->staffProfile?->id);

        return back()->with('status', 'Private attachment stored.');
    }

    public function link(Request $request, Business $business, Client $client, ClientAttachment $attachment, ClientAttachmentService $attachments): RedirectResponse
    {
        abort_unless($client->business_id === $business->id && $attachment->business_id === $business->id && $attachment->client_id === $client->id, 404);
        $this->authorize('viewAttachments', $client);
        if ($attachment->visibility === 'sensitive') {
            abort_unless($request->user()->can('viewSensitive', $client), 403);
        }
        $issued = $attachments->issueDownload($attachment);

        return back()->with('status', 'A private download link is available for 15 minutes.')
            ->with('attachment_url', route('client-attachments.download', $issued['token']));
    }
}
