<?php

namespace App\Domain\Communications\Services;

use App\Domain\ClientRecords\Models\Client;
use App\Domain\ClientRecords\Models\ClientConsent;
use App\Domain\Communications\Models\CommunicationSetting;
use App\Domain\Communications\Models\CommunicationSuppression;

class CommunicationConsentService
{
    /** @return array{allowed:bool,reason:?string,basis:string} */
    public function decision(CommunicationSetting $settings, ?Client $client, string $channel, string $destination, string $category, string $legalBasis): array
    {
        $hash = self::destinationHash($channel, $destination);
        if (! $this->validDestination($channel, $destination)) {
            return ['allowed' => false, 'reason' => 'invalid_destination', 'basis' => $legalBasis];
        }
        $suppressed = CommunicationSuppression::query()->where('business_id', $settings->business_id)->where('channel', $channel)
            ->where('recipient_hash', $hash)->whereNull('released_at')->where(fn ($query) => $query->where('scope', 'all')->orWhere('scope', $category))->exists();
        if ($suppressed) {
            return ['allowed' => false, 'reason' => 'destination_suppressed', 'basis' => $legalBasis];
        }
        if ($category === 'marketing') {
            if (! $settings->marketing_enabled || ! $client || $client->marketing_status !== 'subscribed' || ! $this->prefers($client, $channel)) {
                return ['allowed' => false, 'reason' => 'marketing_consent_missing', 'basis' => 'explicit_consent_required'];
            }
            $legalBasis = 'explicit_marketing_consent';
        }
        if ($channel === 'whatsapp') {
            $optedIn = $client && ClientConsent::query()->where('business_id', $settings->business_id)->where('client_id', $client->id)
                ->where('type', 'whatsapp')->latest('occurred_at')->value('status') === 'granted';
            $explicitRequestWithoutProfile = ! $client && $legalBasis === 'explicit_channel_request';
            if (! $optedIn && ! $explicitRequestWithoutProfile) {
                return ['allowed' => false, 'reason' => 'whatsapp_opt_in_missing', 'basis' => 'explicit_channel_opt_in_required'];
            }
        }

        return ['allowed' => true, 'reason' => null, 'basis' => $legalBasis];
    }

    public function recordWhatsAppOptIn(Client $client, string $source, ?int $appointmentId = null, string $wording = 'Send appointment and service updates to this mobile number on WhatsApp.'): ClientConsent
    {
        return ClientConsent::query()->create([
            'business_id' => $client->business_id, 'client_id' => $client->id, 'appointment_id' => $appointmentId,
            'type' => 'whatsapp', 'status' => 'granted', 'source' => $source, 'policy_version' => 'IN-en-IN-2026-08',
            'wording' => $wording, 'evidence' => ['channel' => 'whatsapp'], 'occurred_at' => now(),
        ]);
    }

    public function suppress(int $businessId, ?Client $client, string $channel, string $destination, string $scope, string $reason, string $source): CommunicationSuppression
    {
        return CommunicationSuppression::query()->create([
            'business_id' => $businessId, 'client_id' => $client?->id, 'channel' => $channel,
            'recipient_hash' => self::destinationHash($channel, $destination), 'scope' => $scope,
            'reason' => $reason, 'source' => $source, 'suppressed_at' => now(),
        ]);
    }

    public function unsubscribe(Client $client, string $channel, string $source = 'secure_link'): void
    {
        $destination = $channel === 'email' ? $client->email : $client->mobile;
        if ($destination) {
            $this->suppress($client->business_id, $client, $channel, $destination, 'marketing', 'unsubscribed', $source);
        }
        $client->forceFill(['marketing_status' => 'withdrawn'])->save();
        ClientConsent::query()->create([
            'business_id' => $client->business_id, 'client_id' => $client->id, 'type' => 'marketing',
            'status' => 'withdrawn', 'source' => $source, 'policy_version' => 'IN-en-IN-2026-08',
            'wording' => 'Marketing unsubscribe', 'evidence' => ['channel' => $channel], 'occurred_at' => now(),
        ]);
    }

    public static function destinationHash(string $channel, string $destination): string
    {
        $normalized = $channel === 'email' ? mb_strtolower(trim($destination)) : preg_replace('/[^+\d]/', '', $destination);

        return hash('sha256', $channel.'|'.$normalized);
    }

    private function validDestination(string $channel, string $destination): bool
    {
        return match ($channel) {
            'email' => filter_var($destination, FILTER_VALIDATE_EMAIL) !== false,
            'whatsapp' => preg_match('/^\+[1-9]\d{7,14}$/', preg_replace('/[^+\d]/', '', $destination)) === 1,
            default => false,
        };
    }

    private function prefers(Client $client, string $channel): bool
    {
        $preferences = $client->communication_preferences ?? [];

        return in_array($channel, $preferences, true) || ($preferences[$channel] ?? false) === true;
    }
}
