<?php

namespace App\Domain\ClientRecords\Services;

use App\Domain\ClientRecords\Models\Client;
use App\Domain\ClientRecords\Models\ClientDuplicateCandidate;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Membership;
use App\Support\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClientMergeService
{
    private const RELATION_TABLES = [
        'appointments', 'client_consents', 'client_notes', 'client_form_requests',
        'client_form_submissions', 'client_attachments', 'client_privacy_requests',
        'communication_intents', 'communication_messages', 'communication_action_links', 'communication_suppressions',
        'sales', 'deposits', 'payment_intents',
    ];

    public function __construct(private readonly AuditWriter $audit) {}

    /** @return array<string,mixed> */
    public function preview(ClientDuplicateCandidate $candidate, Client $survivor, Membership $actor): array
    {
        $this->authorize($candidate, $survivor, $actor);
        $duplicate = $this->duplicate($candidate, $survivor);

        return [
            'candidate_public_id' => (string) $candidate->id,
            'survivor' => ['public_id' => $survivor->public_id, 'name' => $survivor->name, 'version' => $survivor->version],
            'duplicate' => ['public_id' => $duplicate->public_id, 'name' => $duplicate->name, 'version' => $duplicate->version],
            'relationship_counts' => collect(self::RELATION_TABLES)
                ->merge(['client_tag_assignments', 'client_preferred_services'])
                ->mapWithKeys(fn ($table) => [$table => DB::table($table)->where('business_id', $candidate->business_id)->where('client_id', $duplicate->id)->count()])->all(),
            'fields_filled_from_duplicate' => collect(['email', 'mobile', 'date_of_birth', 'preferred_staff_profile_id', 'referral_source'])
                ->filter(fn ($field) => blank($survivor->{$field}) && filled($duplicate->{$field}))->values()->all(),
            'consent_history_preserved' => true,
            'financial_and_audit_records_repointed_when_present' => true,
        ];
    }

    public function merge(ClientDuplicateCandidate $candidate, Client $survivor, Membership $actor, int $survivorVersion, int $duplicateVersion, string $reason): Client
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'A merge reason is required.']);
        }

        return DB::transaction(function () use ($candidate, $survivor, $actor, $survivorVersion, $duplicateVersion, $reason): Client {
            $candidate = ClientDuplicateCandidate::query()->where('business_id', $candidate->business_id)->lockForUpdate()->findOrFail($candidate->id);
            $survivor = Client::query()->where('business_id', $candidate->business_id)->lockForUpdate()->findOrFail($survivor->id);
            $this->authorize($candidate, $survivor, $actor);
            $duplicate = Client::query()->where('business_id', $candidate->business_id)->lockForUpdate()->findOrFail($this->duplicate($candidate, $survivor)->id);
            if ($candidate->status !== 'pending' || $survivor->version !== $survivorVersion || $duplicate->version !== $duplicateVersion) {
                throw ValidationException::withMessages(['version' => 'The duplicate review changed. Refresh the merge preview.']);
            }

            $preview = $this->preview($candidate, $survivor, $actor);
            foreach (self::RELATION_TABLES as $table) {
                DB::table($table)->where('business_id', $candidate->business_id)->where('client_id', $duplicate->id)->update(['client_id' => $survivor->id]);
            }
            DB::table('client_tag_assignments')->where('business_id', $candidate->business_id)->where('client_id', $duplicate->id)
                ->get()->each(fn ($tag) => DB::table('client_tag_assignments')->insertOrIgnore([
                    'business_id' => $candidate->business_id, 'client_id' => $survivor->id,
                    'client_tag_id' => $tag->client_tag_id, 'created_at' => now(), 'updated_at' => now(),
                ]));
            DB::table('client_tag_assignments')->where('business_id', $candidate->business_id)->where('client_id', $duplicate->id)->delete();
            DB::table('client_preferred_services')->where('business_id', $candidate->business_id)->where('client_id', $duplicate->id)
                ->get()->each(fn ($service) => DB::table('client_preferred_services')->insertOrIgnore([
                    'business_id' => $candidate->business_id, 'client_id' => $survivor->id,
                    'service_id' => $service->service_id, 'created_at' => now(), 'updated_at' => now(),
                ]));
            DB::table('client_preferred_services')->where('business_id', $candidate->business_id)->where('client_id', $duplicate->id)->delete();

            $fill = [];
            foreach (['email', 'normalized_email', 'mobile', 'normalized_mobile', 'date_of_birth', 'preferred_staff_profile_id', 'referral_source'] as $field) {
                if (blank($survivor->{$field}) && filled($duplicate->{$field})) {
                    $fill[$field] = $duplicate->{$field};
                }
            }
            $survivor->forceFill([...$fill, 'version' => $survivor->version + 1])->save();
            $duplicate->forceFill(['status' => 'merged', 'merged_into_client_id' => $survivor->id, 'version' => $duplicate->version + 1])->save();
            $candidate->forceFill([
                'status' => 'merged', 'surviving_client_id' => $survivor->id,
                'reviewed_by_membership_id' => $actor->id, 'reviewed_at' => now(), 'preview_snapshot' => $preview,
            ])->save();

            $this->audit->write('client.merge_completed', $survivor->business, $actor->user, $survivor, $reason, [], [
                'survivor_public_id' => $survivor->public_id,
                'merged_client_public_id' => $duplicate->public_id,
                'relationship_counts' => $preview['relationship_counts'],
            ], [], 'client_records');

            return $survivor->fresh();
        }, 3);
    }

    private function authorize(ClientDuplicateCandidate $candidate, Client $survivor, Membership $actor): void
    {
        abort_unless($actor->business_id === $candidate->business_id && $survivor->business_id === $candidate->business_id, 404);
        abort_unless($actor->hasPermissionTo(PermissionName::ClientMerge->value, 'web'), 403);
        abort_unless(in_array($survivor->id, [$candidate->first_client_id, $candidate->second_client_id], true), 422);
    }

    private function duplicate(ClientDuplicateCandidate $candidate, Client $survivor): Client
    {
        $id = $candidate->first_client_id === $survivor->id ? $candidate->second_client_id : $candidate->first_client_id;

        return Client::query()->where('business_id', $candidate->business_id)->findOrFail($id);
    }
}
