<?php

namespace App\Http\Controllers\Shop\ClientRecords;

use App\Domain\ClientRecords\Models\Client;
use App\Domain\ClientRecords\Models\ClientPrivacyRequest;
use App\Domain\ClientRecords\Services\ClientAttachmentService;
use App\Domain\ClientRecords\Services\ClientPrivacyWorkflowService;
use App\Domain\PlatformAccess\Models\Business;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClientPrivacyController extends Controller
{
    public function store(Request $request, Business $business, Client $client, ClientPrivacyWorkflowService $privacy): RedirectResponse
    {
        abort_unless($client->business_id === $business->id, 404);
        $this->authorize('managePrivacy', $client);
        $data = $request->validate([
            'type' => ['required', Rule::in(ClientPrivacyWorkflowService::TYPES)], 'details' => ['nullable', 'array'],
            'details.changes' => ['nullable', 'array'], 'details.changes.name' => ['nullable', 'string', 'max:255'],
            'details.changes.email' => ['nullable', 'email', 'max:255'], 'details.changes.mobile' => ['nullable', 'string', 'max:32'],
            'details.changes.date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'details.changes.communication_preferences' => ['nullable', 'array', 'max:3'],
            'details.changes.communication_preferences.*' => ['string', 'distinct', 'in:email,sms,whatsapp'],
            'details.consent_type' => ['nullable', 'string', Rule::in(['marketing', 'photography', 'treatment'])],
            'details.reason' => ['nullable', 'string', 'max:2000'],
        ]);
        $details = match ($data['type']) {
            'export' => [],
            'correction' => ['changes' => collect(data_get($data, 'details.changes', []))->filter(fn ($value) => filled($value))->all()],
            'consent_withdrawal' => ['consent_type' => data_get($data, 'details.consent_type')],
            'deletion_anonymization' => ['reason' => data_get($data, 'details.reason')],
        };
        if (($data['type'] === 'correction' && $details['changes'] === [])
            || ($data['type'] === 'consent_withdrawal' && blank($details['consent_type']))
            || ($data['type'] === 'deletion_anonymization' && blank($details['reason']))) {
            throw ValidationException::withMessages(['details' => 'Provide the details required for this privacy request.']);
        }
        $privacy->submit($client, $data['type'], $details);

        return back()->with('status', 'Privacy request logged with a review deadline.');
    }

    public function process(Request $request, Business $business, Client $client, ClientPrivacyRequest $privacyRequest, ClientPrivacyWorkflowService $privacy, TenantContext $context): RedirectResponse
    {
        abort_unless($client->business_id === $business->id && $privacyRequest->business_id === $business->id && $privacyRequest->client_id === $client->id, 404);
        $this->authorize('managePrivacy', $client);
        $processed = match ($privacyRequest->type) {
            'export' => $privacy->completeExport($privacyRequest, $context->membership()),
            'correction' => $privacy->completeCorrection($privacyRequest, $context->membership()),
            'consent_withdrawal' => $privacy->completeConsentWithdrawal($privacyRequest, $context->membership()),
            'deletion_anonymization' => $privacy->reviewDeletionAnonymization($privacyRequest, $context->membership()),
        };
        $response = back()->with('status', $processed->status === 'blocked_policy'
            ? 'Request reviewed and safely held pending the approved retention policy.'
            : 'Privacy request completed with evidence recorded.');
        if ($processed->exportAttachment) {
            $issued = app(ClientAttachmentService::class)->issueDownload($processed->exportAttachment);
            $response->with('privacy_export_url', route('client-attachments.download', $issued['token']));
        }

        return $response;
    }
}
