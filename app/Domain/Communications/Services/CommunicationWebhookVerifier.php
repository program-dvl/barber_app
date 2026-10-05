<?php

namespace App\Domain\Communications\Services;

use App\Domain\Communications\Models\CommunicationSenderProfile;
use Illuminate\Http\Request;

class CommunicationWebhookVerifier
{
    public function verifyResend(Request $request): bool
    {
        $secret = (string) config('communications.resend.webhook_secret');
        $id = (string) $request->header('svix-id');
        $timestamp = (string) $request->header('svix-timestamp');
        $signatures = (string) $request->header('svix-signature');
        if ($secret === '' || $id === '' || ! ctype_digit($timestamp) || abs(now()->timestamp - (int) $timestamp) > 300) {
            return false;
        }
        $encodedSecret = str_starts_with($secret, 'whsec_') ? substr($secret, 6) : $secret;
        $decoded = base64_decode($encodedSecret, true);
        if ($decoded === false) {
            return false;
        }
        $expected = base64_encode(hash_hmac('sha256', $id.'.'.$timestamp.'.'.$request->getContent(), $decoded, true));

        return collect(explode(' ', $signatures))->contains(function (string $signature) use ($expected): bool {
            [, $candidate] = array_pad(explode(',', $signature, 2), 2, '');

            return $candidate !== '' && hash_equals($expected, $candidate);
        });
    }

    public function verifyTwilio(Request $request): bool
    {
        $provided = (string) $request->header('X-Twilio-Signature');
        if ($provided === '') {
            return false;
        }
        $data = $request->fullUrl();
        $parameters = $request->post();
        ksort($parameters, SORT_STRING);
        foreach ($parameters as $key => $value) {
            $data .= $key.(is_array($value) ? implode('', $value) : $value);
        }
        $tokens = collect([
            (string) config('communications.twilio.webhook_auth_token'),
            (string) config('communications.twilio.auth_token'),
        ])->filter();
        if ($accountSid = $request->string('AccountSid')->toString()) {
            $profile = CommunicationSenderProfile::query()->where('provider_account_sid_hash', hash('sha256', $accountSid))->where('status', 'active')->first();
            if ($profile?->webhook_auth_token) {
                $tokens->push($profile->webhook_auth_token);
            }
        }

        return $tokens->unique()->contains(function (string $token) use ($data, $provided): bool {
            $expected = base64_encode(hash_hmac('sha1', $data, $token, true));

            return hash_equals($expected, $provided);
        });
    }
}
