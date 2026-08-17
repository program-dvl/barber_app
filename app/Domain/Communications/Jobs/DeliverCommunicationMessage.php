<?php

namespace App\Domain\Communications\Jobs;

use App\Domain\Communications\Models\CommunicationMessage;
use App\Domain\Communications\Services\CommunicationDeliveryService;
use App\Support\Jobs\DispatchesInTenant;
use App\Support\Jobs\TenantAwareJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeliverCommunicationMessage implements ShouldQueue, TenantAwareJob
{
    use Dispatchable, DispatchesInTenant, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public int $timeout = 30;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public int $messageId, ?string $correlationId = null)
    {
        $message = CommunicationMessage::query()->findOrFail($messageId);
        $this->initializeTenantPayload($message->business_id, $correlationId);
        $this->onQueue('communications');
    }

    public function handle(CommunicationDeliveryService $delivery): void
    {
        $message = CommunicationMessage::query()->forBusiness($this->businessId)->findOrFail($this->messageId);
        $delivery->deliver($message);
    }
}
