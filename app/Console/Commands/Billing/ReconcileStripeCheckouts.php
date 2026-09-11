<?php

namespace App\Console\Commands\Billing;

use App\Domain\Billing\Models\BillingCheckoutAttempt;
use App\Domain\Billing\Services\StripeCheckoutReconciler;
use App\Domain\PlatformAccess\Models\Business;
use Illuminate\Console\Command;
use Throwable;

class ReconcileStripeCheckouts extends Command
{
    protected $signature = 'billing:reconcile-stripe-checkouts
                            {--business= : Restrict reconciliation to one Business public ID}
                            {--limit=100 : Maximum pending attempts to inspect}';

    protected $description = 'Reconcile pending Stripe Checkout Sessions through authenticated provider evidence.';

    public function handle(StripeCheckoutReconciler $reconciler): int
    {
        $businessId = null;
        if ($publicId = trim((string) $this->option('business'))) {
            $businessId = Business::query()->where('public_id', $publicId)->value('id');
            if (! $businessId) {
                $this->components->error('The requested Business was not found.');

                return self::FAILURE;
            }
        }

        $processed = 0;
        $failed = 0;
        BillingCheckoutAttempt::query()
            ->where('provider', 'stripe')
            ->whereIn('status', ['pending', 'processing'])
            ->when($businessId, fn ($query) => $query->where('business_id', $businessId))
            ->oldest('created_at')
            ->limit(max(1, min(500, (int) $this->option('limit'))))
            ->get()
            ->each(function (BillingCheckoutAttempt $attempt) use ($reconciler, &$processed, &$failed): void {
                try {
                    $reconciler->reconcile($attempt);
                    $processed++;
                } catch (Throwable $exception) {
                    report($exception);
                    $attempt->update(['last_checked_at' => now()]);
                    $failed++;
                }
            });

        $this->components->info("Reconciled {$processed} pending Stripe Checkout attempt(s); {$failed} require retry or support review.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
