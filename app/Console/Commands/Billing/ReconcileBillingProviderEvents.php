<?php

namespace App\Console\Commands\Billing;

use App\Domain\Billing\Models\BillingProviderEvent;
use App\Domain\Billing\Services\PaddleWebhookProcessor;
use App\Domain\Billing\Services\StripeWebhookProcessor;
use Illuminate\Console\Command;
use Throwable;

class ReconcileBillingProviderEvents extends Command
{
    protected $signature = 'billing:reconcile-provider-events {--limit=100}';

    protected $description = 'Replay signature-verified pending or failed subscription events safely.';

    public function handle(StripeWebhookProcessor $stripe, PaddleWebhookProcessor $paddle): int
    {
        $processed = 0;
        $failed = 0;
        BillingProviderEvent::query()->where('signature_verified', true)->whereIn('status', ['pending', 'failed'])
            ->oldest('provider_created_at')->limit((int) $this->option('limit'))->get()
            ->each(function (BillingProviderEvent $event) use ($stripe, $paddle, &$processed, &$failed): void {
                try {
                    match ($event->provider) {
                        'stripe' => $stripe->receiveVerified($event->payload),
                        'paddle' => $paddle->receiveVerified($event->payload),
                        default => throw new \LogicException('No reviewed billing replay adapter.'),
                    };
                    $processed++;
                } catch (Throwable) {
                    $failed++;
                }
            });
        $this->info("Reconciled {$processed} event(s); {$failed} still failed.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
