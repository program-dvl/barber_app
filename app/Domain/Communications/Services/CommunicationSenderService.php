<?php

namespace App\Domain\Communications\Services;

use App\Domain\Communications\Models\CommunicationSenderProfile;
use App\Domain\Communications\Models\CommunicationSetting;
use App\Domain\PlatformAccess\Models\Business;

class CommunicationSenderService
{
    public function resolve(Business|int $business, string $channel, CommunicationSetting $settings): ?CommunicationSenderProfile
    {
        $business = $business instanceof Business ? $business : Business::query()->findOrFail($business);
        $mode = $settings->sender_mode === 'branded' ? 'branded' : 'platform';
        $profile = CommunicationSenderProfile::query()->where('business_id', $business->id)
            ->where('channel', $channel)->where('mode', $mode)->where('status', 'active')->first();

        if ($profile || $mode === 'branded') {
            return $profile;
        }

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

        return CommunicationSenderProfile::query()->updateOrCreate([
            'business_id' => $business->id, 'channel' => $channel, 'mode' => 'platform',
        ], [
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
            ],
            'verified_at' => $available ? now() : null,
        ]);
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
            'branded_onboarding_available' => in_array($transport, ['fake', 'live'], true),
            'profiles' => CommunicationSenderProfile::query()->where('business_id', $business->id)->where('channel', 'sms')->get()
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
