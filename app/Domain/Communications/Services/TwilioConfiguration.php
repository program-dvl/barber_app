<?php

namespace App\Domain\Communications\Services;

use App\Domain\Communications\Exceptions\CommunicationProviderException;

final class TwilioConfiguration
{
    public static function statusCallback(): string
    {
        return self::callback('status_callback_url', 'communications.webhooks.twilio');
    }

    public static function inboundCallback(): string
    {
        return self::callback('inbound_callback_url', 'communications.webhooks.twilio.inbound');
    }

    private static function callback(string $key, string $route): string
    {
        $base = trim((string) config('communications.twilio.webhook_base_url'));
        if ($base !== '' && (parse_url($base, PHP_URL_QUERY) !== null || parse_url($base, PHP_URL_FRAGMENT) !== null)) {
            throw new CommunicationProviderException('twilio_callback_not_configured', false);
        }
        $url = config('communications.twilio.'.$key) ?: ($base !== '' ? rtrim($base, '/').route($route, [], false) : route($route));
        if (! filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_USER) !== null
            || parse_url($url, PHP_URL_PASS) !== null || parse_url($url, PHP_URL_FRAGMENT) !== null
            || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new CommunicationProviderException('twilio_callback_not_configured', false);
        }
        if (config('communications.transport_mode') === 'live' && parse_url($url, PHP_URL_SCHEME) !== 'https') {
            throw new CommunicationProviderException('twilio_callback_not_configured', false);
        }

        return $url;
    }
}
