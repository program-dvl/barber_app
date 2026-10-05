<?php

namespace App\Domain\Communications\Providers;

use App\Domain\Communications\Contracts\MobileChannelProvider;
use App\Domain\Communications\Data\OutboundCommunication;
use App\Domain\Communications\Data\ProviderSendResult;

class FakeMobileProvider implements MobileChannelProvider
{
    public function name(): string
    {
        return 'fake';
    }

    public function channel(): string
    {
        return 'mobile';
    }

    public function send(OutboundCommunication $message): ProviderSendResult
    {
        $suffix = substr(hash('sha256', $message->channel.'|'.$message->idempotencyKey), 0, 28);

        return new ProviderSendResult('FAKE'.strtoupper($suffix), 'fake-'.$suffix, 'sent');
    }
}
