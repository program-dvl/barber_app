<?php

namespace App\Domain\Communications\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class SesNotificationVerifier
{
    public function verify(array $payload): bool
    {
        $topic = (string) config('communications.ses.sns_topic_arn');
        if ($topic === '' || ($payload['TopicArn'] ?? '') !== $topic || ! in_array(($payload['Type'] ?? ''), ['Notification', 'SubscriptionConfirmation'], true)) {
            return false;
        }
        $region = explode(':', $topic)[3] ?? '';
        $certificateUrl = (string) ($payload['SigningCertURL'] ?? '');
        if (! preg_match('~^https://sns\.([a-z0-9-]+)\.amazonaws\.com/SimpleNotificationService-[A-Za-z0-9]+\.pem$~D', $certificateUrl, $match) || $match[1] !== $region) {
            return false;
        }
        $algorithm = match ((string) ($payload['SignatureVersion'] ?? '')) {
            '1' => OPENSSL_ALGO_SHA1, '2' => OPENSSL_ALGO_SHA256, default => null,
        };
        $signature = base64_decode((string) ($payload['Signature'] ?? ''), true);
        if (! $algorithm || ! $signature) {
            return false;
        }
        $canonical = '';
        $fields = $payload['Type'] === 'Notification' ? ['Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type'] : ['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'];
        foreach ($fields as $field) {
            if ($field === 'Subject' && ! array_key_exists($field, $payload)) {
                continue;
            }
            if (! isset($payload[$field]) || ! is_string($payload[$field]) || $payload[$field] === '') {
                return false;
            }
            $canonical .= $field."\n".$payload[$field]."\n";
        }
        try {
            $response = Http::withOptions(['allow_redirects' => false])->timeout(5)->get($certificateUrl);
            if (! $response->successful() || strlen($response->body()) > 65536) {
                return false;
            }
            $certificate = openssl_x509_read($response->body());
            if (! $certificate) {
                return false;
            }
            $details = openssl_x509_parse($certificate);
            if (! $details || ($details['validFrom_time_t'] ?? PHP_INT_MAX) > time() || ($details['validTo_time_t'] ?? 0) < time()) {
                return false;
            }

            return openssl_verify($canonical, $signature, $certificate, $algorithm) === 1;
        } catch (Throwable) {
            return false;
        }
    }
}
