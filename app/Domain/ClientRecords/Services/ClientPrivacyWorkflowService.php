<?php

namespace App\Domain\ClientRecords\Services;

use App\Domain\ClientRecords\Models\Client;
use App\Domain\ClientRecords\Models\ClientConsent;
use App\Domain\ClientRecords\Models\ClientPrivacyRequest;
use App\Domain\PlatformAccess\Models\Membership;
use App\Support\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClientPrivacyWorkflowService
{
    public const TYPES = ['export', 'correction', 'consent_withdrawal', 'deletion_anonymization'];

    public function __construct(
        private readonly ClientIdentityService $identity,
        private readonly ClientAttachmentService $attachments,
        private readonly AuditWriter $audit,
    ) {}

    /** @param array<string,mixed> $details */
    public function submit(Client $client, string $type, array $details = []): ClientPrivacyRequest
    {
        if (! in_array($type, self::TYPES, true)) {
            throw ValidationException::withMessages(['type' => 'Choose a supported privacy request.']);
        }
        $request = ClientPrivacyRequest::query()->create([
            'business_id' => $client->business_id,
            'client_id' => $client->id,
            'type' => $type,
            'status' => 'submitted',
            'request_details' => $details,
            'requested_at' => now(),
            'due_at' => now()->addDays(30),
        ]);
        $this->audit->write('client.privacy_request_submitted', $client->business, null, $request, null, [], [
            'type' => $type, 'due_at' => $request->due_at->toDateString(),
        ], [], 'privacy');

        return $request;
    }

    public function completeExport(ClientPrivacyRequest $request, Membership $reviewer): ClientPrivacyRequest
    {
        $this->assertReviewable($request, $reviewer, 'export');

        return DB::transaction(function () use ($request, $reviewer): ClientPrivacyRequest {
            $request = ClientPrivacyRequest::query()->where('business_id', $request->business_id)->lockForUpdate()->findOrFail($request->id);
            $client = Client::query()->where('business_id', $request->business_id)->with([
                'appointments.serviceLines.segments', 'consents', 'formSubmissions', 'attachments', 'communications.intent',
            ])->findOrFail($request->client_id);
            $dataset = $this->exportDataset($client);
            $json = json_encode($dataset, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $attachment = $this->attachments->storePrivacyExport($client, $json);
            $request->forceFill([
                'status' => 'completed', 'reviewed_by_membership_id' => $reviewer->id,
                'export_attachment_id' => $attachment->id, 'reviewed_at' => now(), 'completed_at' => now(),
                'result_summary' => ['sections' => array_keys($dataset['data']), 'omissions' => $dataset['manifest']['intentionally_omitted'], 'sha256' => $attachment->sha256],
                'version' => $request->version + 1,
            ])->save();
            $this->audit->write('client.privacy_export_completed', $client->business, $reviewer->user, $request, null, [], [
                'request_public_id' => $request->public_id, 'export_sha256' => $attachment->sha256,
            ], [], 'privacy');

            return $request->fresh('exportAttachment');
        }, 3);
    }

    public function completeCorrection(ClientPrivacyRequest $request, Membership $reviewer): ClientPrivacyRequest
    {
        $this->assertReviewable($request, $reviewer, 'correction');
        $details = $request->request_details ?? [];
        $changes = collect($details['changes'] ?? [])->only(['name', 'email', 'mobile', 'date_of_birth', 'communication_preferences'])->all();
        if ($changes === []) {
            throw ValidationException::withMessages(['changes' => 'Correction details are required.']);
        }
        $client = Client::query()->where('business_id', $request->business_id)->findOrFail($request->client_id);
        $this->identity->updateProfile($client, $changes, $client->version, 'Approved privacy correction '.$request->public_id);

        return $this->complete($request, $reviewer, ['corrected_fields' => array_keys($changes)], 'client.privacy_correction_completed');
    }

    public function completeConsentWithdrawal(ClientPrivacyRequest $request, Membership $reviewer): ClientPrivacyRequest
    {
        $this->assertReviewable($request, $reviewer, 'consent_withdrawal');
        $client = Client::query()->where('business_id', $request->business_id)->findOrFail($request->client_id);
        ClientConsent::query()->create([
            'business_id' => $client->business_id,
            'client_id' => $client->id,
            'type' => (string) data_get($request->request_details, 'consent_type', 'marketing'),
            'status' => 'withdrawn',
            'source' => 'privacy_request',
            'evidence' => ['privacy_request_public_id' => $request->public_id],
            'occurred_at' => now(),
        ]);
        if (data_get($request->request_details, 'consent_type', 'marketing') === 'marketing') {
            $client->forceFill(['marketing_status' => 'withdrawn', 'version' => $client->version + 1])->save();
        }

        return $this->complete($request, $reviewer, ['consent_type' => data_get($request->request_details, 'consent_type', 'marketing')], 'client.consent_withdrawn');
    }

    public function reviewDeletionAnonymization(ClientPrivacyRequest $request, Membership $reviewer): ClientPrivacyRequest
    {
        $this->assertReviewable($request, $reviewer, 'deletion_anonymization');
        $client = Client::query()->where('business_id', $request->business_id)->findOrFail($request->client_id);
        $preview = [
            'appointments_retained' => $client->appointments()->count(),
            'consent_evidence_retained' => $client->consents()->count(),
            'form_submissions_retained' => $client->formSubmissions()->count(),
            'attachments_pending_policy' => $client->attachments()->count(),
            'financial_and_audit_history' => 'must_remain_append_only',
            'destructive_changes_applied' => false,
            'policy_dependency' => ['OPEN-02', 'OPEN-10'],
        ];
        $request->forceFill([
            'status' => 'blocked_policy', 'reviewed_by_membership_id' => $reviewer->id,
            'reviewed_at' => now(), 'decision_reason' => 'Launch jurisdiction and retention schedule are not yet approved.',
            'result_summary' => $preview, 'version' => $request->version + 1,
        ])->save();
        $this->audit->write('client.deletion_anonymization_policy_blocked', $client->business, $reviewer->user, $request, $request->decision_reason, [], $preview, [], 'privacy');

        return $request->fresh();
    }

    /** @return array<string,mixed> */
    public function exportDataset(Client $client): array
    {
        $client->loadMissing(['appointments.serviceLines.segments', 'consents', 'formSubmissions', 'attachments', 'communications.intent']);

        return [
            'manifest' => [
                'schema' => 'good-hours-client-export-v1',
                'generated_at_utc' => now()->utc()->toIso8601String(),
                'client_public_id' => $client->public_id,
                'intentionally_omitted' => ['passwords_and_tokens', 'internal_staff_notes_and_warnings', 'audit_security_metadata', 'provider_credentials'],
            ],
            'data' => [
                'profile' => collect($client->only(['public_id', 'name', 'email', 'mobile', 'date_of_birth', 'preferences', 'communication_preferences', 'referral_source', 'marketing_status']))->all(),
                'appointments' => $client->appointments->map(fn ($appointment) => [
                    'reference' => $appointment->booking_reference, 'status' => $appointment->status,
                    'starts_at_utc' => $appointment->starts_at_utc->toIso8601String(),
                    'services' => $appointment->serviceLines->map(fn ($line) => ['name' => $line->name, 'performer_public_ids' => $line->segments->pluck('staff_profile_id')->filter()->unique()->values()->all()])->all(),
                ])->all(),
                'consents' => $client->consents->map->only(['type', 'status', 'source', 'policy_version', 'wording', 'evidence', 'occurred_at'])->all(),
                'submitted_forms' => $client->formSubmissions->map(fn ($submission) => [
                    'public_id' => $submission->public_id, 'wording_snapshot' => $submission->wording_snapshot,
                    'answers' => $submission->answers, 'signature_recorded' => $submission->signature_hash !== null,
                    'submitted_identity_snapshot' => $submission->submitted_identity_snapshot, 'submitted_at' => $submission->submitted_at->toIso8601String(),
                ])->all(),
                'attachment_metadata' => $client->attachments->reject(fn ($attachment) => $attachment->kind === 'privacy_export')->map->only(['public_id', 'kind', 'original_name', 'mime_type', 'size_bytes', 'created_at'])->values()->all(),
                'communications' => $client->communications->map(fn ($message) => [
                    'intent' => $message->intent->intent_type, 'channel' => $message->channel, 'category' => $message->category,
                    'legal_basis' => $message->legal_basis, 'status' => $message->status, 'queued_at' => $message->queued_at?->toIso8601String(),
                    'sent_at' => $message->sent_at?->toIso8601String(), 'delivered_at' => $message->delivered_at?->toIso8601String(),
                ])->all(),
                'financial_history' => ['status' => 'checkout_ledger_not_yet_installed', 'records' => []],
            ],
        ];
    }

    /** @param array<string,mixed> $summary */
    private function complete(ClientPrivacyRequest $request, Membership $reviewer, array $summary, string $auditAction): ClientPrivacyRequest
    {
        $request->forceFill([
            'status' => 'completed', 'reviewed_by_membership_id' => $reviewer->id,
            'reviewed_at' => now(), 'completed_at' => now(), 'result_summary' => $summary,
            'version' => $request->version + 1,
        ])->save();
        $this->audit->write($auditAction, $request->client->business, $reviewer->user, $request, null, [], $summary, [], 'privacy');

        return $request->fresh();
    }

    private function assertReviewable(ClientPrivacyRequest $request, Membership $reviewer, string $type): void
    {
        abort_unless($request->business_id === $reviewer->business_id, 404);
        if ($request->type !== $type || $request->status !== 'submitted') {
            throw ValidationException::withMessages(['request' => 'This privacy request cannot be processed in its current state.']);
        }
    }
}
