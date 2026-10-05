<?php

namespace App\Domain\Communications\Jobs;

use App\Domain\Communications\Services\OperationalCommunicationService;
use App\Domain\SchedulingOperations\Models\OperationalNotificationEvent;
use App\Support\Jobs\DispatchesInTenant;
use App\Support\Jobs\TenantAwareJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessOperationalCommunicationEvent implements ShouldQueue, TenantAwareJob
{
    use Dispatchable, DispatchesInTenant, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(public int $eventId, int $businessId)
    {
        $this->initializeTenantPayload($businessId);
        $this->onQueue('communications');
    }

    public function handle(OperationalCommunicationService $communications): void
    {
        $event = OperationalNotificationEvent::query()
            ->where('business_id', $this->businessId)
            ->find($this->eventId);

        if (! $event || $event->status !== 'pending') {
            return;
        }

        try {
            $communications->process($event);
        } catch (Throwable $error) {
            // The sync driver is intended for local development. The booking has
            // already committed, so a provider outage must not turn it into a
            // misleading booking failure. The scheduled sweep will retry due work.
            if (config('queue.default') === 'sync') {
                report($error);

                return;
            }

            throw $error;
        }
    }
}
