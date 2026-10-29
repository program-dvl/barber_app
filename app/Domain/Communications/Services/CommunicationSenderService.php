<?php

namespace App\Domain\Communications\Services;

use App\Domain\Communications\Models\CommunicationSenderProfile;
use App\Domain\Communications\Models\CommunicationSetting;
use App\Domain\PlatformAccess\Models\Business;
use App\Support\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;

class CommunicationSenderService
{
    public function resolve(Business|int $business, string $channel, CommunicationSetting $settings): ?CommunicationSenderProfile
    {
        $business = $business instanceof Business ? $business : Business::query()->findOrFail($business);

        // New client notifications always use the platform identity. Retain
        // historical branded profiles for callback routing and audit evidence.
        return $this->ensurePlatformProfile($business, $channel);
    }

    public function ensurePlatformProfile(Business $business, string $channel): CommunicationSenderProfile
    {
        $mode = (string) config('communications.transport_mode', 'fake');
        $identifier = match ($channel) {
            'sms' => $mode === 'live' ? config('communications.twilio.sms_from') : config('communications.twilio.test_sms_from'),
            'whatsapp' => $mode === 'live' ? config('communications.twilio.whatsapp_from') : config('communications.twilio.whatsapp_sandbox_from'),
            default => null,
        };
        $available = $mode === 'fake'
            || ($channel === 'sms' && $mode === 'twilio_test' && $this->smsTestReady())
            || ($channel === 'whatsapp' && $mode === 'whatsapp_sandbox' && $this->whatsAppSandboxReady())
            || ($mode === 'live' && $this->liveReady($identifier));

        return DB::transaction(function () use ($business, $channel, $mode, $identifier, $available): CommunicationSenderProfile {
            Business::query()->whereKey($business->id)->lockForUpdate()->firstOrFail();
            $existing = CommunicationSenderProfile::query()->where('business_id', $business->id)
                ->where('channel', $channel)->where('mode', 'platform')->lockForUpdate()->first();
            $accountHash = filled(config('communications.twilio.account_sid')) ? hash('sha256', (string) config('communications.twilio.account_sid')) : null;
            $service = $channel === 'sms' ? config('communications.twilio.messaging_service_sid') : null;
            if ($existing && $existing->sender_identifier === ($identifier ?: 'ClipperDesk')
                && $existing->provider === ($mode === 'fake' ? 'fake' : 'twilio')
                && $existing->status === ($available ? 'active' : 'pending')
                && (bool) data_get($existing->capabilities, 'simulation') === ($mode !== 'live')
                && data_get($existing->capabilities, 'messaging_service_sid') === $service
                && $existing->provider_account_sid_hash === $accountHash) {
                return $existing;
            }
            if ($existing) {
                // Retain the captured identity. Old queued simulation work must
                // not silently acquire a live sender during configuration repair.
                $existing->update(['mode' => 'retired_'.$existing->id, 'status' => 'retired']);
            }
            $profile = CommunicationSenderProfile::query()->create([
                'business_id' => $business->id, 'channel' => $channel, 'mode' => 'platform',
                'provider' => $mode === 'fake' ? 'fake' : 'twilio',
                'status' => $available ? 'active' : 'pending',
                'country_code' => $business->country_code,
                'display_name' => 'ClipperDesk for '.$business->name,
                'sender_identifier' => $identifier ?: 'ClipperDesk',
                'sender_identifier_hash' => self::identifierHash($channel, $identifier ?: 'ClipperDesk'),
                'provider_account_sid_hash' => filled(config('communications.twilio.account_sid'))
                    ? hash('sha256', (string) config('communications.twilio.account_sid'))
                    : null,
                'capabilities' => [
                    'outbound' => true,
                    'inbound' => $channel === 'sms',
                    'templates' => false,
                    'simulation' => $mode !== 'live',
                    'messaging_service_sid' => $channel === 'sms' ? config('communications.twilio.messaging_service_sid') : null,
                ],
                'verified_at' => $available ? now() : null,
            ]);

            if ($existing) {
                app(AuditWriter::class)->write('communication.platform_sender_refreshed', $business, target: $profile,
                    after: ['previous_profile_id' => $existing->id, 'profile_id' => $profile->id, 'channel' => $channel, 'transport' => $mode]);
            }

            return $profile;
        });
    }

    public static function identifierHash(string $channel, string $identifier): string
    {
        $normalized = preg_replace('/^(?:whatsapp:|sms:)/', '', trim($identifier));

        return hash('sha256', $channel.'|'.$normalized);
    }

    /** @return array<string, mixed> */
    public function readiness(Business $business): array
    {
        $transport = (string) config('communications.transport_mode', 'fake');
        $transportReady = match ($transport) {
            'fake' => true,
            'twilio_test' => $this->smsTestReady(),
            'live' => $this->liveReady(config('communications.twilio.sms_from')),
            default => false,
        };

        return [
            'transport_mode' => $transport,
            'transport_ready' => $transportReady,
            'network_sends' => $transport !== 'fake',
            'live_send_enabled' => (bool) config('communications.live_send_enabled'),
            'sms_test_configured' => $this->smsTestReady(),
            'branded_onboarding_available' => false,
            'profiles' => CommunicationSenderProfile::query()->where('business_id', $business->id)->where('channel', 'sms')->where('mode', 'platform')->get()
                ->map(fn (CommunicationSenderProfile $profile) => [
                    'public_id' => $profile->public_id,
                    'channel' => $profile->channel,
                    'mode' => $profile->mode,
                    'provider' => $profile->provider,
                    'status' => $profile->status,
                    'display_name' => $profile->display_name,
                    'sender' => $profile->maskedIdentifier(),
                    'capabilities' => $profile->capabilities ?? [],
                    'verified_at' => $profile->verified_at?->toIso8601String(),
                ])->values(),
        ];
    }

    private function smsTestReady(): bool
    {
        return filled(config('communications.twilio.test_account_sid'))
            && filled(config('communications.twilio.test_auth_token'))
            && filled(config('communications.twilio.test_sms_from'));
    }

    private function whatsAppSandboxReady(): bool
    {
        return config('communications.whatsapp_sandbox_send_enabled')
            && $this->liveCredentialsReady()
            && filled(config('communications.twilio.whatsapp_sandbox_from'));
    }

    private function liveReady(?string $sender): bool
    {
        return config('communications.live_send_enabled')
            && $this->liveCredentialsReady()
            && filled($sender);
    }

    private function liveCredentialsReady(): bool
    {
        $account = filled(config('communications.twilio.account_sid'));
        $apiKey = filled(config('communications.twilio.api_key_sid'))
            && filled(config('communications.twilio.api_key_secret'));
        $authToken = filled(config('communications.twilio.auth_token'));

        return $account && ($apiKey || $authToken);
    }
}
