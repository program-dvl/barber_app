<?php

namespace App\Console\Commands\Communications;

use App\Domain\Communications\Services\TwilioConfiguration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CheckTwilioConfiguration extends Command
{
    protected $signature = 'communications:check-twilio {--provider-read : Check Twilio account resources with GET requests only}';

    protected $description = 'Check SMS configuration and sender approvals without sending or dispatching messages.';

    public function handle(): int
    {
        $ok = true;
        $report = function (string $name, bool $pass, string $detail = '') use (&$ok): void {
            $ok = $ok && $pass;
            $this->line(($pass ? 'PASS' : 'BLOCK').' '.$name.($detail ? ': '.$detail : ''));
        };
        $report('Live SMS selected', config('communications.transport_mode') === 'live' && config('communications.live_send_enabled'));
        $report('Account credentials present', filled(config('communications.twilio.account_sid'))
            && ((filled(config('communications.twilio.api_key_sid')) && filled(config('communications.twilio.api_key_secret'))) || filled(config('communications.twilio.auth_token'))));
        $report('Callback verification token present', filled(config('communications.twilio.webhook_auth_token')) || filled(config('communications.twilio.auth_token')));
        $sender = (string) config('communications.twilio.sms_from');
        $service = (string) config('communications.twilio.messaging_service_sid');
        $report('SMS sender configured', (bool) preg_match('/^\+[1-9]\d{7,14}$/', $sender) && $sender !== '+15005550006');
        $report('Messaging Service configured', (bool) preg_match('/^MG[0-9a-f]{32}$/i', $service));
        try {
            $status = TwilioConfiguration::statusCallback();
            $inbound = TwilioConfiguration::inboundCallback();
            $report('Public HTTPS callbacks configured', str_starts_with($status, 'https://') && str_starts_with($inbound, 'https://'));
        } catch (\Throwable) {
            $report('Public HTTPS callbacks configured', false);
        }
        $this->line('Queue connection: '.config('queue.default').'; schedule communications:process-events every minute.');
        $this->line('WhatsApp and cross-channel fallback remain disabled for this launch.');
        if (! $this->option('provider-read')) {
            $this->line('Provider ownership, approval and delivery are unverified. Use --provider-read for read-only resource checks.');

            return $ok ? self::SUCCESS : self::FAILURE;
        }
        $account = (string) config('communications.twilio.account_sid');
        $key = (string) config('communications.twilio.api_key_sid');
        $secret = (string) config('communications.twilio.api_key_secret');
        $get = function (string $url, array $query = [], bool $token = false) use ($account, $key, $secret): array {
            $response = Http::withBasicAuth($token || $key === '' ? $account : $key, $token || $key === '' ? (string) config('communications.twilio.auth_token') : $secret)
                ->withOptions(['allow_redirects' => false])->timeout(12)->get($url, $query);
            if (! $response->successful()) {
                throw new \RuntimeException('Twilio read returned HTTP '.$response->status());
            }

            return $response->json();
        };
        try {
            $numbers = $get('https://api.twilio.com/2010-04-01/Accounts/'.$account.'/IncomingPhoneNumbers.json', ['PageSize' => 1000]);
            $owned = collect($numbers['incoming_phone_numbers'] ?? [])->firstWhere('phone_number', $sender);
            $report('Owned SMS-capable number', $owned && data_get($owned, 'capabilities.sms') === true);
            if ($service !== '') {
                $settings = $get('https://messaging.twilio.com/v1/Services/'.$service);
                $pool = $get('https://messaging.twilio.com/v1/Services/'.$service.'/PhoneNumbers', ['PageSize' => 1000]);
                $report('SMS number in service sender pool', collect($pool['phone_numbers'] ?? [])->contains('phone_number', $sender));
                $report('Service status callback matches app', isset($status) && ($settings['status_callback'] ?? '') === $status);
                $report('Inbound compliance webhook configured', isset($inbound) &&
                    (empty($settings['use_inbound_webhook_on_number']) ? ($settings['inbound_request_url'] ?? '') === $inbound : ($owned['sms_url'] ?? '') === $inbound));
            }
            if ($owned && preg_match('/^\+18(00|33|44|55|66|77|88)/', $sender)) {
                $verification = $get('https://messaging.twilio.com/v1/Tollfree/Verifications', ['TollfreePhoneNumberSid' => $owned['sid']], true);
                $states = collect($verification['verifications'] ?? [])->pluck('status');
                $report('Toll-free SMS approval', $states->contains('TWILIO_APPROVED'), $states->implode(', ') ?: 'No approved registration found');
            }
        } catch (\Throwable $error) {
            // Never print raw HTTP exceptions: URLs/auth/request details may be private.
            $report('Twilio resource checks', false, $error instanceof \RuntimeException && preg_match('/^Twilio read returned HTTP \d{3}$/', $error->getMessage()) ? $error->getMessage() : 'Could not complete the read-only check');
        }
        $this->line('No messages sent. Geographic/carrier registration and real recipient delivery still require separate verification.');

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
