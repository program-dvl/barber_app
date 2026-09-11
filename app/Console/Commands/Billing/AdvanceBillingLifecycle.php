<?php

namespace App\Console\Commands\Billing;

use App\Domain\Billing\Services\SubscriptionLifecycleManager;
use Illuminate\Console\Command;

class AdvanceBillingLifecycle extends Command
{
    protected $signature = 'billing:advance-lifecycle';

    protected $description = 'Apply due plan changes and progressive subscription restrictions.';

    public function handle(SubscriptionLifecycleManager $lifecycle): int
    {
        $trials = $lifecycle->advanceTrials(now());
        $changes = $lifecycle->applyDuePlanChanges(now());
        $restrictions = $lifecycle->advanceDunning(now());
        $this->info("Expired {$trials['expired']} trial(s), queued {$trials['notices']} trial notice(s), applied {$changes} plan change(s), and restricted {$restrictions} past-due subscription(s).");

        return self::SUCCESS;
    }
}
