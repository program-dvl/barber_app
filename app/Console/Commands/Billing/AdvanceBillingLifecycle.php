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
        $changes = $lifecycle->applyDuePlanChanges(now());
        $restrictions = $lifecycle->advanceDunning(now());
        $this->info("Applied {$changes} plan change(s) and {$restrictions} restriction(s).");

        return self::SUCCESS;
    }
}
