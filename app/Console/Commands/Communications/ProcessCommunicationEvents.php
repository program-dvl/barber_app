<?php

namespace App\Console\Commands\Communications;

use App\Domain\Communications\Services\OperationalCommunicationService;
use Illuminate\Console\Command;

class ProcessCommunicationEvents extends Command
{
    protected $signature = 'communications:process-events {--business=} {--limit=100}';

    protected $description = 'Create idempotent communication intents from pending operational events.';

    public function handle(OperationalCommunicationService $communications): int
    {
        $count = $communications->processPending($this->option('business') ? (int) $this->option('business') : null, max(1, min(500, (int) $this->option('limit'))));
        $this->info("Processed {$count} operational communication events.");

        return self::SUCCESS;
    }
}
