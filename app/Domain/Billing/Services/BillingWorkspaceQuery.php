<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\BillingInvoice;
use App\Domain\Billing\Models\BusinessEntitlementOverride;
use App\Domain\Billing\Models\BusinessSubscription;
use App\Domain\Billing\Models\EntitlementDefinition;
use App\Domain\PlatformAccess\Models\Business;

/** Local, bounded billing projection. Visiting the overview never calls Stripe. */
class BillingWorkspaceQuery
{
    public const FEATURES = ['locations.max', 'staff.max', 'messaging.monthly_allowance', 'messaging.branded_sender', 'messaging.two_way', 'deposits.enabled', 'inventory.enabled', 'reporting.advanced', 'branding.custom', 'support.priority', 'exports.enabled'];

    public function entitlements(Business $business, BusinessSubscription $subscription): array
    {
        $values = $subscription->plan->entitlements()->with('definition')
            ->where('effective_from', '<=', now())
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhere('effective_until', '>', now()))
            ->orderBy('effective_from')->get()->mapWithKeys(fn ($item) => [$item->definition->key => $item->value]);
        if ($subscription->capacity_snapshot) {
            $capacity = $subscription->capacity_snapshot;
            $values = collect($capacity['features'] + ['locations.max' => $capacity['locations'], 'staff.max' => $capacity['staff'], 'messaging.monthly_allowance' => $capacity['sms_monthly_allowance']]);
        }
        $overrides = BusinessEntitlementOverride::query()->where('business_id', $business->id)
            ->where('effective_from', '<=', now())
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhere('effective_until', '>', now()))
            ->orderBy('effective_from')->get();
        $definitions = EntitlementDefinition::query()->whereIn('id', $overrides->pluck('entitlement_definition_id'))->pluck('key', 'id');
        foreach ($overrides as $override) {
            if (isset($definitions[$override->entitlement_definition_id])) {
                $values[$definitions[$override->entitlement_definition_id]] = $override->value;
            }
        }

        return collect(self::FEATURES)->mapWithKeys(fn ($key) => [$key => $values->get($key)])->all();
    }

    public function subscription(BusinessSubscription $subscription): array
    {
        return $subscription->only(['status', 'restriction_level', 'billing_interval', 'trial_started_at', 'trial_ends_at',
            'current_period_started_at', 'current_period_ends_at', 'grace_ends_at', 'cancel_at', 'canceled_at', 'ended_at',
            'export_available_until', 'payment_method_type', 'payment_method_last_four', 'payment_method_expiry_month', 'payment_method_expiry_year', 'billing_checked_at', 'account_checked_at', 'billing_name', 'billing_email', 'customer_balance_minor', 'balance_currency', 'version']) + [
                'billing_plan_id' => $subscription->billing_plan_id,
                'has_billing_account' => filled($subscription->provider_customer_id),
                'has_provider_subscription' => filled($subscription->provider_subscription_id),
                'plan' => $subscription->plan->only(['id', 'name', 'code']),
                'capacity' => $subscription->capacity_snapshot,
                'price' => $subscription->capacity_snapshot ? ['id' => $subscription->billing_plan_price_id, 'amount_minor' => $subscription->capacity_snapshot['total_minor'], 'currency' => $subscription->capacity_snapshot['currency'], 'billing_interval' => $subscription->capacity_snapshot['billing_interval']] : $subscription->price?->only(['id', 'amount_minor', 'currency', 'billing_interval']),
            ];
    }

    public function attention(BusinessSubscription $subscription): array
    {
        return $subscription->invoices()->whereIn('status', ['open', 'uncollectible'])
            ->selectRaw('currency, SUM(COALESCE(amount_remaining_minor, CASE WHEN amount_due_minor > amount_paid_minor THEN amount_due_minor - amount_paid_minor ELSE 0 END)) as amount_minor')
            ->selectRaw('MAX(last_payment_failed_at) as failed_at')
            ->selectRaw('MIN(CASE WHEN status = ? AND next_payment_attempt_at > ? THEN next_payment_attempt_at END) as next_retry_at', ['open', now()])
            ->groupBy('currency')->get()->filter(fn ($item) => $item->amount_minor > 0)
            ->map(fn ($item) => ['currency' => $item->currency, 'amount_minor' => (int) $item->amount_minor, 'failed_at' => $item->failed_at, 'next_retry_at' => $item->next_retry_at])->values()->all();
    }

    public function invoice(BillingInvoice $invoice): array
    {
        return $invoice->only(['public_id', 'number', 'status', 'currency', 'subtotal_minor', 'discount_minor', 'tax_minor',
            'total_minor', 'amount_due_minor', 'amount_paid_minor', 'amount_remaining_minor', 'issued_at', 'due_at', 'paid_at',
            'last_payment_failed_at', 'next_payment_attempt_at']) + [
                'hosted_url' => $this->secureLink($invoice->hosted_url),
                'pdf_url' => $this->secureLink($invoice->pdf_url),
            ];
    }

    private function secureLink(?string $url): ?string
    {
        return $url && parse_url($url, PHP_URL_SCHEME) === 'https'
            && in_array(parse_url($url, PHP_URL_HOST), ['invoice.stripe.com', 'pay.stripe.com', 'billing.stripe.com', 'files.stripe.com'], true) ? $url : null;
    }
}
