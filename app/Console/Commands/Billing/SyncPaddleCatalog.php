<?php

namespace App\Console\Commands\Billing;

use App\Domain\Billing\Enums\BillingInterval;
use App\Domain\Billing\Models\BillingPlan;
use App\Domain\Billing\Models\BillingPlanEntitlement;
use App\Domain\Billing\Models\BillingPlanPrice;
use App\Domain\Billing\Models\EntitlementDefinition;
use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/** Synchronizes the only approved Good Hours SaaS catalog with Paddle. */
class SyncPaddleCatalog extends Command
{
    /**
     * Change plan names, prices, and entitlement values here, then re-run with
     * --apply. A changed commercial price creates a new provider price and
     * retires the local price mapping; it never rewrites billed history.
     *
     * @var string
     */
    protected $signature = 'billing:sync-paddle-catalog
                            {--apply : Create or synchronize the Paddle catalog and local effective-dated mappings}
                            {--dry-run : Show the intended changes without writing to Paddle or the database}';

    protected $description = 'Create or safely synchronize Good Hours Starter and Pro recurring Paddle prices.';

    public function handle(): int
    {
        if ($this->option('apply') && $this->option('dry-run')) {
            $this->components->error('Choose either --apply or --dry-run, not both.');

            return self::INVALID;
        }

        $apply = (bool) $this->option('apply');
        if (! $apply) {
            $this->components->warn('Dry run only. Re-run with --apply after reviewing this output; that action creates or changes Paddle catalog records.');
        }

        $client = $this->request();
        foreach ($this->catalog() as $definition) {
            $this->syncPlan($client, $definition, $apply);
        }

        $this->newLine();
        $this->components->info($apply
            ? 'Paddle catalog synchronized. Newly created price IDs are now the active local checkout mappings.'
            : 'Dry run completed. No Paddle or local billing records were changed.');

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $definition */
    private function syncPlan(PendingRequest $client, array $definition, bool $apply): void
    {
        $plan = BillingPlan::query()->where('code', $definition['code'])->first();
        $this->line('<fg=cyan>'.$definition['name'].'</>');

        if ($apply) {
            $plan ??= BillingPlan::query()->create([
                'public_id' => (string) Str::ulid(),
                'code' => $definition['code'],
                'name' => $definition['name'],
                'description' => $definition['description'],
                'is_active' => true,
                'is_trial_default' => false,
                'available_from' => now(),
            ]);
            $plan->update([
                'name' => $definition['name'],
                'description' => $definition['description'],
                'is_active' => true,
                'available_until' => null,
            ]);
        }

        $product = $this->findProduct($client, $definition['code']);
        if ($product) {
            $this->line('  Product: '.$product['id'].' (existing)');
            if ($apply) {
                $product = $client->patch('/products/'.$product['id'], $this->productPayload($definition))->throw()->json('data');
            }
        } elseif ($apply) {
            $product = $client->post('/products', $this->productPayload($definition))->throw()->json('data');
            $this->line('  Product: '.$product['id'].' (created)');
        } else {
            $this->line('  Product: would create');
        }

        foreach ($definition['prices'] as $interval => $amountMinor) {
            $this->syncPrice($client, $plan, $definition, (string) $interval, (int) $amountMinor, $product['id'] ?? null, $apply);
        }

        if ($apply) {
            $this->syncEntitlements($plan, $definition['entitlements']);
        }
    }

    /** @param array<string, mixed> $definition */
    private function syncPrice(PendingRequest $client, ?BillingPlan $plan, array $definition, string $interval, int $amountMinor, ?string $productId, bool $apply): void
    {
        $current = $plan
            ? BillingPlanPrice::query()
                ->where('billing_plan_id', $plan->getKey())
                ->where('provider', 'paddle')
                ->where('billing_interval', $interval)
                ->where('currency', 'USD')
                ->where('is_active', true)
                ->whereNull('effective_until')
                ->latest('effective_from')
                ->first()
            : null;
        $providerPrice = $current ? $this->getPrice($client, $current->provider_price_id) : null;

        if ($current && $providerPrice && (int) $current->amount_minor === $amountMinor) {
            $this->line("  {$interval}: {$current->provider_price_id} (current)");
            if ($apply) {
                $client->patch('/prices/'.$current->provider_price_id, $this->priceUpdatePayload($definition, $interval))->throw();
            }

            return;
        }

        if (! $apply) {
            $reason = $current ? 'replace outdated or unavailable price' : 'create price';
            $this->line("  {$interval}: would {$reason} at ".$this->money($amountMinor));

            return;
        }

        $created = $client->post('/prices', $this->pricePayload($definition, $interval, $amountMinor, $productId))->throw()->json('data');
        // Database timestamps are second-precision in the supported local
        // engines. Always reserve the following second for a replacement so a
        // migration and its first sync cannot share one effective timestamp.
        $effectiveFrom = now()->addSecond();

        DB::transaction(function () use ($plan, $current, $created, $interval, $amountMinor, $effectiveFrom): void {
            if ($current) {
                $current->update(['is_active' => false, 'effective_until' => $effectiveFrom]);
            }

            BillingPlanPrice::query()->create([
                'billing_plan_id' => $plan->getKey(),
                'billing_interval' => BillingInterval::from($interval),
                'currency' => 'USD',
                'amount_minor' => $amountMinor,
                'provider' => 'paddle',
                'provider_price_id' => $created['id'],
                'is_active' => true,
                'effective_from' => $effectiveFrom,
            ]);
        });

        $this->line("  {$interval}: {$created['id']} (created at ".$this->money($amountMinor).')');
    }

    /** @param array<string, bool|int> $values */
    private function syncEntitlements(BillingPlan $plan, array $values): void
    {
        foreach ($values as $key => $value) {
            $definition = EntitlementDefinition::query()->where('key', $key)->firstOrFail();
            $current = BillingPlanEntitlement::query()
                ->where('billing_plan_id', $plan->getKey())
                ->where('entitlement_definition_id', $definition->getKey())
                ->whereNull('effective_until')
                ->latest('effective_from')
                ->first();
            if ($current && $current->value === $value) {
                continue;
            }

            $now = now();
            if ($current) {
                $current->update(['effective_until' => $now]);
            }
            BillingPlanEntitlement::query()->create([
                'billing_plan_id' => $plan->getKey(),
                'entitlement_definition_id' => $definition->getKey(),
                'value' => $value,
                'effective_from' => $now,
                'change_reason' => 'Paddle catalog synchronization.',
            ]);
        }
    }

    /** @return array<string, mixed>|null */
    private function findProduct(PendingRequest $client, string $code): ?array
    {
        $products = $client->get('/products', ['per_page' => 200])->throw()->json('data', []);

        return collect($products)->first(fn (array $product): bool => data_get($product, 'custom_data.good_hours_plan_code') === $code);
    }

    /** @return array<string, mixed>|null */
    private function getPrice(PendingRequest $client, string $id): ?array
    {
        try {
            return $client->get('/prices/'.$id)->throw()->json('data');
        } catch (RequestException $exception) {
            if ($exception->response?->status() === 404) {
                return null;
            }

            throw $exception;
        }
    }

    /** @param array<string, mixed> $definition
     * @return array<string, mixed>
     */
    private function productPayload(array $definition): array
    {
        return [
            'name' => $definition['name'],
            'description' => $definition['description'],
            'tax_category' => 'saas',
            'custom_data' => ['good_hours_plan_code' => $definition['code']],
        ];
    }

    /** @param array<string, mixed> $definition
     * @return array<string, mixed>
     */
    private function pricePayload(array $definition, string $interval, int $amountMinor, ?string $productId): array
    {
        return [
            'product_id' => $productId,
            'name' => $definition['name'].' '.str($interval)->headline(),
            'description' => $definition['name'].' '.$interval.' Good Hours subscription.',
            'billing_cycle' => ['interval' => $interval === 'annual' ? 'year' : 'month', 'frequency' => 1],
            'unit_price' => ['amount' => (string) $amountMinor, 'currency_code' => 'USD'],
            'tax_mode' => 'account_setting',
            'quantity' => ['minimum' => 1, 'maximum' => 1],
            'custom_data' => ['good_hours_plan_code' => $definition['code'], 'good_hours_interval' => $interval],
        ];
    }

    /** @param array<string, mixed>
     * @return array<string, mixed>
     */
    private function priceUpdatePayload(array $definition, string $interval): array
    {
        return [
            'name' => $definition['name'].' '.str($interval)->headline(),
            'description' => $definition['name'].' '.$interval.' Good Hours subscription.',
            'custom_data' => ['good_hours_plan_code' => $definition['code'], 'good_hours_interval' => $interval],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function catalog(): array
    {
        return [
            [
                'code' => 'starter',
                'name' => 'Good Hours Starter',
                'description' => 'For solo and small shops ready to run their day with confidence.',
                // Update these values, then run: php artisan billing:sync-paddle-catalog --apply
                'prices' => ['monthly' => 5000, 'annual' => 50000],
                'entitlements' => [
                    'locations.max' => 1,
                    'staff.max' => 2,
                    'messaging.monthly_allowance' => 0,
                    'deposits.enabled' => false,
                    'inventory.enabled' => false,
                    'reporting.advanced' => false,
                    'branding.custom' => false,
                    'support.priority' => false,
                    'exports.enabled' => true,
                    'billing.manage' => true,
                ],
            ],
            [
                'code' => 'pro',
                'name' => 'Good Hours Pro',
                'description' => 'For growing salons that need deeper operational control and insight.',
                // Update these values, then run: php artisan billing:sync-paddle-catalog --apply
                'prices' => ['monthly' => 10000, 'annual' => 100000],
                'entitlements' => [
                    'locations.max' => 3,
                    'staff.max' => 20,
                    'messaging.monthly_allowance' => 1000,
                    'deposits.enabled' => true,
                    'inventory.enabled' => true,
                    'reporting.advanced' => true,
                    'branding.custom' => true,
                    'support.priority' => true,
                    'exports.enabled' => true,
                    'billing.manage' => true,
                ],
            ],
        ];
    }

    private function request(): PendingRequest
    {
        $apiKey = trim((string) config('billing.paddle.api_key'));
        if ($apiKey === '' || str_starts_with($apiKey, 'your-paddle-')) {
            throw new RuntimeException('Set PADDLE_API_KEY to a valid Paddle API key before syncing the catalog.');
        }

        return Http::baseUrl((string) config('billing.paddle.api_url'))
            ->acceptJson()
            ->asJson()
            ->withToken($apiKey)
            ->withHeaders(['Paddle-Version' => '1']);
    }

    private function money(int $amountMinor): string
    {
        return '$'.number_format($amountMinor / 100, 2);
    }
}
