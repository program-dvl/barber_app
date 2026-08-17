<?php

namespace App\Domain\Communications\Contracts;

use App\Domain\Communications\Data\OutboundCommunication;
use App\Domain\Communications\Data\ProviderSendResult;

interface EmailChannelProvider
{
    public function name(): string;

    public function send(OutboundCommunication $message): ProviderSendResult;
}
