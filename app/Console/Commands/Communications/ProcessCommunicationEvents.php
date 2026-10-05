<?php

namespace App\Console\Commands\Communications;

use App\Domain\Communications\Services\CommunicationDeliveryDispatcher;
use App\Domain\Communications\Services\OperationalCommunicationService;
use Illuminate\Console\Command;

class ProcessCommunicationEvents extends Command
{
    protected $signature = 'communications:process-events {--business=} {--limit=100}';

    protected $description = 'Process operational events and dispatch due or retryable communication messages.';

    public function handle(OperationalCommunicationService $communications, CommunicationDeliveryDispatcher $deliveries): int
    {
        $businessId = $this->option('business') ? (int) $this->option('business') : null;
        $limit = max(1, min(500, (int) $this->option('limit')));
        $events = $communications->processPending($businessId, $limit);
        $messages = $deliveries->dispatchDue($businessId, $limit);
        $this->info("Processed {$events} operational events and dispatched {$messages} due messages.");

        return self::SUCCESS;
    }
}
