<?php

namespace App\Domain\Communications\Providers;

use App\Domain\Communications\Contracts\MobileChannelProvider;
use App\Domain\Communications\Data\OutboundCommunication;
use App\Domain\Communications\Data\ProviderSendResult;
use App\Domain\Communications\Exceptions\CommunicationProviderException;

class ConfiguredMobileChannelProvider implements MobileChannelProvider
{
    public function __construct(
        private readonly FakeMobileProvider $fake,
        private readonly TwilioSmsProvider $sms,
        private readonly TwilioWhatsAppProvider $whatsApp,
    ) {}

    public function name(): string
    {
        return config('communications.transport_mode', 'fake') === 'fake' ? 'fake' : 'twilio';
    }

    public function channel(): string
    {
        return 'mobile';
    }

    public function send(OutboundCommunication $message): ProviderSendResult
    {
        if (! in_array($message->channel, config('communications.client_mobile_channels', ['sms']), true)) {
            throw new CommunicationProviderException('mobile_channel_not_enabled', false);
        }

        $mode = (string) config('communications.transport_mode', 'fake');
        if ($mode === 'fake') {
            return $this->fake->send($message);
        }
        if ($message->channel === 'sms') {
            return $this->sms->send($message);
        }
        if ($message->channel === 'whatsapp') {
            if ($mode === 'twilio_test') {
                throw new CommunicationProviderException('whatsapp_not_supported_by_twilio_test_credentials', false);
            }

            return $this->whatsApp->send($message);
        }

        throw new CommunicationProviderException('unsupported_mobile_channel', false);
    }
}
