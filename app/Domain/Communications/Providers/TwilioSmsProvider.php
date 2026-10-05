<?php

namespace App\Domain\Communications\Providers;

use App\Domain\Communications\Contracts\MobileChannelProvider;
use App\Domain\Communications\Data\OutboundCommunication;
use App\Domain\Communications\Data\ProviderSendResult;
use App\Domain\Communications\Exceptions\CommunicationProviderException;
use App\Domain\Communications\Models\CommunicationSenderProfile;
use Illuminate\Support\Facades\Http;

class TwilioSmsProvider implements MobileChannelProvider
{
    public function name(): string
    {
        return 'twilio';
    }

    public function channel(): string
    {
        return 'sms';
    }

    public function send(OutboundCommunication $message): ProviderSendResult
    {
        $mode = (string) config('communications.transport_mode', 'fake');
        if ($mode === 'live' && ! config('communications.live_send_enabled')) {
            throw new CommunicationProviderException('live_send_not_enabled', false);
        }

        $profile = $message->senderProfileId
            ? CommunicationSenderProfile::query()->where('business_id', $message->businessId)->find($message->senderProfileId)
            : null;
        if ($message->senderProfileId && (! $profile || $profile->status !== 'active' || $profile->channel !== 'sms')) {
            throw new CommunicationProviderException('sender_not_ready', false);
        }
        $test = $mode !== 'live';
        $account = $test ? (string) config('communications.twilio.test_account_sid') : (string) ($profile?->provider_account_sid ?: config('communications.twilio.account_sid'));
        $username = $test ? $account : (string) ($profile?->provider_api_key_sid ?: config('communications.twilio.api_key_sid') ?: $account);
        $secret = $test ? (string) config('communications.twilio.test_auth_token') : (string) ($profile?->provider_api_key_secret ?: config('communications.twilio.api_key_secret') ?: config('communications.twilio.auth_token'));
        $from = $test ? (string) config('communications.twilio.test_sms_from') : (string) ($profile?->sender_identifier ?: config('communications.twilio.sms_from'));
        if ($account === '' || $username === '' || $secret === '' || $from === '') {
            throw new CommunicationProviderException('provider_not_configured', false);
        }

        $payload = ['To' => $message->destination, 'From' => $from, 'Body' => $message->body];
        if (! $test) {
            $payload['StatusCallback'] = route('communications.webhooks.twilio');
        }
        $response = Http::withBasicAuth($username, $secret)->asForm()->timeout(10)
            ->withHeaders(['X-Correlation-ID' => $message->correlationId])
            ->post('https://api.twilio.com/2010-04-01/Accounts/'.$account.'/Messages.json', $payload);
        if (! $response->successful()) {
            throw new CommunicationProviderException('twilio_http_'.$response->status(), $response->status() === 429);
        }
        $sid = (string) $response->json('sid');
        if ($sid === '') {
            throw new CommunicationProviderException('twilio_missing_message_id', false);
        }

        return new ProviderSendResult($sid, $response->header('Twilio-Request-Id'), (string) ($response->json('status') ?: 'queued'));
    }
}
