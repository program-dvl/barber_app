<?php

namespace App\Domain\Billing\Services;

class PaddleWebhookSignatureVerifier
{
    public function verify(string $payload, ?string $signature): bool
    {
        $secret = (string) config('billing.paddle.webhook_secret');
        if ($secret === '' || ! $signature) {
            return false;
        }
        $parts = collect(explode(';', $signature))->mapWithKeys(function (string $part): array {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            return [$key => $value];
        });
        $timestamp = $parts->get('ts');
        $provided = $parts->get('h1');
        if (! is_string($timestamp) || ! ctype_digit($timestamp) || ! is_string($provided) || abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.':'.$payload, $secret);

        return hash_equals($expected, $provided);
    }
}
