<?php

namespace App\Domain\ClientRecords\Services;

use App\Domain\ClientRecords\Contracts\ClientIdentityLinker;
use App\Domain\ClientRecords\Models\Client;
use App\Domain\ClientRecords\Models\ClientConsent;
use App\Domain\ClientRecords\Models\ClientDuplicateCandidate;
use App\Domain\ClientRecords\Support\ClientIdentityNormalizer;
use App\Domain\Communications\Services\CommunicationActionLinkService;
use App\Domain\PublicBooking\Services\SecureAppointmentLinkService;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Support\Audit\AuditWriter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClientIdentityService implements ClientIdentityLinker
{
    public function __construct(
        private readonly SecureAppointmentLinkService $links,
        private readonly CommunicationActionLinkService $communicationLinks,
        private readonly AuditWriter $audit,
    ) {}

    public function linkAppointment(Appointment $appointment): void
    {
        if ($appointment->client_id) {
            return;
        }

        $normalized = $this->normalize([
            'name' => $appointment->client_name,
            'mobile' => $appointment->client_mobile,
            'email' => $appointment->client_email,
        ]);
        $candidates = $this->contactCandidates($appointment->business_id, $normalized['mobile'], $normalized['email']);
        $safe = $candidates->filter(fn (Client $client) => $client->normalized_name === $normalized['name']);
        $client = $safe->count() === 1 ? $safe->first() : null;

        if (! $client) {
            $client = Client::query()->create([
                'business_id' => $appointment->business_id,
                'name' => trim((string) ($appointment->client_name ?: 'Client')),
                'normalized_name' => $normalized['name'] ?: 'client',
                'email' => $appointment->client_email,
                'normalized_email' => $normalized['email'],
                'mobile' => $appointment->client_mobile,
                'normalized_mobile' => $normalized['mobile'],
                'date_of_birth' => $appointment->client_date_of_birth,
                'communication_preferences' => $appointment->communication_preferences ?? [],
                'referral_source' => $appointment->referral_source,
                'marketing_status' => $appointment->marketing_opt_in ? 'subscribed' : 'unknown',
            ]);
            if ($appointment->marketing_opt_in !== null) {
                ClientConsent::query()->create([
                    'business_id' => $appointment->business_id,
                    'client_id' => $client->id,
                    'appointment_id' => $appointment->id,
                    'type' => 'marketing',
                    'status' => $appointment->marketing_opt_in ? 'granted' : 'declined',
                    'source' => $appointment->source,
                    'policy_version' => data_get($appointment->public_policy_snapshot, 'version'),
                    'wording' => data_get($appointment->public_policy_snapshot, 'marketing_wording'),
                    'evidence' => ['booking_reference' => $appointment->booking_reference],
                    'occurred_at' => $appointment->confirmed_at ?? now(),
                ]);
            }
        }

        $appointment->forceFill(['client_id' => $client->id])->save();
        $preferences = $appointment->communication_preferences ?? [];
        $whatsAppSelected = in_array('whatsapp', $preferences, true) || ($preferences['whatsapp'] ?? false) === true;
        if ($whatsAppSelected && ! ClientConsent::query()->where('business_id', $appointment->business_id)->where('client_id', $client->id)
            ->where('type', 'whatsapp')->where('status', 'granted')->exists()) {
            ClientConsent::query()->create([
                'business_id' => $appointment->business_id, 'client_id' => $client->id, 'appointment_id' => $appointment->id,
                'type' => 'whatsapp', 'status' => 'granted', 'source' => $appointment->source,
                'policy_version' => 'IN-en-IN-2026-08',
                'wording' => data_get($appointment->public_policy_snapshot, 'whatsapp_wording', 'Send appointment and service updates to this mobile number on WhatsApp.'),
                'evidence' => ['booking_reference' => $appointment->booking_reference, 'channel' => 'whatsapp'],
                'occurred_at' => $appointment->confirmed_at ?? now(),
            ]);
        }
        $this->detectDuplicates($client);
    }

    /** @param array{name?:string,mobile?:string,email?:string} $contact */
    public function synchronizeAppointmentContact(Appointment $appointment, array $contact): void
    {
        if (! $appointment->client_id) {
            $this->linkAppointment($appointment);
            $appointment->refresh();
        }
        $client = Client::query()->where('business_id', $appointment->business_id)->findOrFail($appointment->client_id);
        $this->updateProfile($client, [
            'name' => $contact['name'] ?? $client->name,
            'mobile' => $contact['mobile'] ?? $client->mobile,
            'email' => $contact['email'] ?? $client->email,
        ], $client->version, 'secure appointment contact update');
    }

    /** @param array<string,mixed> $attributes */
    public function updateProfile(Client $client, array $attributes, int $expectedVersion, string $reason): Client
    {
        $allowed = collect($attributes)->only([
            'name', 'email', 'mobile', 'date_of_birth', 'preferred_staff_profile_id', 'preferences',
            'communication_preferences', 'referral_source',
        ])->all();
        $normalized = $this->normalize([
            'name' => $allowed['name'] ?? $client->name,
            'email' => $allowed['email'] ?? $client->email,
            'mobile' => $allowed['mobile'] ?? $client->mobile,
        ]);
        $allowed['normalized_name'] = $normalized['name'];
        $allowed['normalized_email'] = $normalized['email'];
        $allowed['normalized_mobile'] = $normalized['mobile'];
        $contactChanged = $normalized['email'] !== $client->normalized_email || $normalized['mobile'] !== $client->normalized_mobile;
        $changedFields = array_values(array_keys($allowed));
        $casted = $client->newInstance();
        $casted->forceFill($allowed);
        $allowed = $casted->getAttributes();

        $updated = Client::query()->where('business_id', $client->business_id)->whereKey($client->id)
            ->where('status', 'active')->where('version', $expectedVersion)
            ->update([...$allowed, 'version' => DB::raw('version + 1'), 'updated_at' => now()]);
        if ($updated !== 1) {
            throw ValidationException::withMessages(['version' => 'This client was changed by someone else. Refresh before saving.']);
        }

        $fresh = $client->fresh();
        if ($contactChanged) {
            Appointment::query()->where('business_id', $client->business_id)->where('client_id', $client->id)
                ->where('starts_at_utc', '>', now())->get()->each(function (Appointment $appointment): void {
                    $this->links->revokeAppointment($appointment);
                    $this->communicationLinks->revokeTarget($appointment->business_id, $appointment);
                });
        }
        $this->audit->write('client.profile_updated', $fresh->business, null, $fresh, $reason, [], [
            'changed_fields' => $changedFields,
            'contact_changed' => $contactChanged,
            'version' => $fresh->version,
        ], [], 'client_records');
        $this->detectDuplicates($fresh);

        return $fresh;
    }

    public function detectDuplicates(Client $client): Collection
    {
        $query = Client::query()->where('business_id', $client->business_id)->where('status', 'active')->whereKeyNot($client->id);
        $query->where(function ($query) use ($client): void {
            $has = false;
            if ($client->normalized_mobile) {
                $query->where('normalized_mobile', $client->normalized_mobile);
                $has = true;
            }
            if ($client->normalized_email) {
                $has ? $query->orWhere('normalized_email', $client->normalized_email) : $query->where('normalized_email', $client->normalized_email);
                $has = true;
            }
            if (! $has) {
                $query->whereRaw('1 = 0');
            }
        });

        return $query->get()->map(function (Client $candidate) use ($client): ClientDuplicateCandidate {
            $reasons = [];
            if ($client->normalized_mobile && $client->normalized_mobile === $candidate->normalized_mobile) {
                $reasons[] = 'same_normalized_mobile';
            }
            if ($client->normalized_email && $client->normalized_email === $candidate->normalized_email) {
                $reasons[] = 'same_normalized_email';
            }
            if ($this->similarNames($client->normalized_name, $candidate->normalized_name)) {
                $reasons[] = 'similar_name';
            }
            $first = min($client->id, $candidate->id);
            $second = max($client->id, $candidate->id);
            $confidence = min(99, count($reasons) * 30 + (in_array('same_normalized_mobile', $reasons, true) ? 10 : 0));

            return ClientDuplicateCandidate::query()->updateOrCreate(
                ['business_id' => $client->business_id, 'first_client_id' => $first, 'second_client_id' => $second],
                ['confidence' => $confidence, 'reasons' => $reasons, 'detected_at' => now()]
            );
        });
    }

    /** @return array{name:string,email:?string,mobile:?string} */
    private function normalize(array $contact): array
    {
        return [
            'name' => ClientIdentityNormalizer::name($contact['name'] ?? null),
            'email' => ClientIdentityNormalizer::email($contact['email'] ?? null),
            'mobile' => ClientIdentityNormalizer::mobile($contact['mobile'] ?? null),
        ];
    }

    private function contactCandidates(int $businessId, ?string $mobile, ?string $email): Collection
    {
        if (! $mobile && ! $email) {
            return new Collection;
        }

        return Client::query()->where('business_id', $businessId)->where('status', 'active')
            ->where(function ($query) use ($mobile, $email): void {
                if ($mobile) {
                    $query->where('normalized_mobile', $mobile);
                }
                if ($email) {
                    $mobile ? $query->orWhere('normalized_email', $email) : $query->where('normalized_email', $email);
                }
            })->get();
    }

    private function similarNames(string $first, string $second): bool
    {
        if ($first === $second) {
            return true;
        }
        if ($first === '' || $second === '') {
            return false;
        }

        return levenshtein($first, $second) <= max(1, (int) floor(max(strlen($first), strlen($second)) * 0.25));
    }
}
