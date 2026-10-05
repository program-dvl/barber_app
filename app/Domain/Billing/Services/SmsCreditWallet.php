<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\BusinessSubscription;
use App\Domain\Billing\Models\SmsCreditPurchase;
use App\Domain\Communications\Models\CommunicationMessage;
use App\Support\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;

class SmsCreditWallet
{
    public function __construct(private readonly EntitlementUsageManager $usage, private readonly EntitlementEvaluator $entitlements) {}

    public function balance(int $businessId): int
    {
        return (int) DB::table('sms_credit_entries')->where('business_id', $businessId)->sum('quantity');
    }

    /** Called only from the verified Stripe edge or an authenticated provider read. */
    public function confirmPurchase(array $session): bool
    {
        $id = data_get($session, 'metadata.sms_purchase_id');
        $purchase = $id ? SmsCreditPurchase::where('public_id', $id)->first() : null;
        if (! $purchase || ($session['mode'] ?? null) !== 'payment' || ($session['id'] ?? null) !== $purchase->provider_session_id) {
            return false;
        }

        return DB::transaction(function () use ($session, $purchase): bool {
            $subscription = BusinessSubscription::where('business_id', $purchase->business_id)->lockForUpdate()->firstOrFail();
            $locked = SmsCreditPurchase::lockForUpdate()->findOrFail($purchase->id);
            if (($session['customer'] ?? null) !== $subscription->provider_customer_id
                || data_get($session, 'metadata.business_public_id') !== $subscription->business->public_id
                || strtoupper($session['currency'] ?? '') !== $locked->quote['currency']
                || (int) ($session['amount_subtotal'] ?? $session['amount_total'] ?? -1) !== $locked->quote['amount_minor']
                || (int) data_get($session, 'total_details.amount_discount', 0) !== 0
                || (int) data_get($session, 'total_details.amount_tax', 0) < 0
                || (int) ($session['amount_total'] ?? -1) !== $locked->quote['amount_minor'] + (int) data_get($session, 'total_details.amount_tax', 0)) {
                return false;
            }
            if (($session['status'] ?? null) === 'expired' && $locked->status !== 'paid') {
                $locked->update(['status' => 'expired']);

                return true;
            }
            if (($session['payment_status'] ?? null) !== 'paid' || ($session['status'] ?? null) !== 'complete') {
                return false;
            }
            $granted = DB::table('sms_credit_entries')->insertOrIgnore([
                'business_id' => $locked->business_id, 'source_key' => 'purchase:'.$locked->id,
                'quantity' => $locked->quote['credits'], 'kind' => 'purchase', 'created_at' => now(),
            ]);
            $locked->update(['status' => 'paid', 'paid_at' => $locked->paid_at ?? now()]);
            if ($granted) {
                app(AuditWriter::class)->write('sms.credits.purchased', $subscription->business, target: $locked,
                    after: ['credits' => $locked->quote['credits'], 'subtotal_minor' => $locked->quote['amount_minor'], 'currency' => $locked->quote['currency']], source: 'provider');
            }

            return true;
        }, 3);
    }

    public function reserve(CommunicationMessage $message, int $quantity): bool
    {
        return DB::transaction(function () use ($message, $quantity): bool {
            $subscription = $message->business->subscription()->lockForUpdate()->firstOrFail();
            $prior = DB::table('sms_usage_reservations')->where('communication_message_id', $message->id)->lockForUpdate()->first();
            if ($prior?->status === 'reserved') {
                return true;
            }
            if ($quantity < 1 || ! $this->entitlements->decide($message->business, 'messaging.monthly_allowance', 'create')->allowed) {
                return false;
            }
            $included = min($quantity, max(0, (int) $this->entitlements->value($message->business, 'messaging.monthly_allowance') - $this->entitlements->usage($message->business, 'messaging.monthly_allowance')));
            $purchased = $quantity - $included;
            if ($purchased > $this->balance($subscription->business_id)) {
                return false;
            }
            if ($included && ! $this->usage->reserve($message->business, 'messaging.monthly_allowance', $included)) {
                return false;
            }
            $generation = $prior ? $prior->generation + 1 : 1;
            if ($purchased) {
                DB::table('sms_credit_entries')->insert([
                    'business_id' => $message->business_id, 'source_key' => 'message:'.$message->id.':'.$generation.':reserve',
                    'quantity' => -$purchased, 'kind' => 'consumption', 'created_at' => now(),
                ]);
            }
            DB::table('sms_usage_reservations')->updateOrInsert(['communication_message_id' => $message->id], [
                'business_id' => $message->business_id, 'generation' => $generation, 'quantity' => $quantity,
                'included_quantity' => $included, 'purchased_quantity' => $purchased,
                'status' => 'reserved', 'charged_at' => now(), 'created_at' => $prior?->created_at ?? now(), 'updated_at' => now(),
            ]);

            return true;
        }, 3);
    }

    public function release(CommunicationMessage $message): void
    {
        DB::transaction(function () use ($message): void {
            $message->business->subscription()->lockForUpdate()->firstOrFail();
            $reservation = DB::table('sms_usage_reservations')->where('communication_message_id', $message->id)->lockForUpdate()->first();
            if (! $reservation || $reservation->status !== 'reserved') {
                return;
            }
            if ($reservation->included_quantity) {
                $this->usage->release($message->business, 'messaging.monthly_allowance', $reservation->charged_at, $reservation->included_quantity);
            }
            if ($reservation->purchased_quantity) {
                DB::table('sms_credit_entries')->insert([
                    'business_id' => $message->business_id, 'source_key' => 'message:'.$message->id.':'.$reservation->generation.':release',
                    'quantity' => $reservation->purchased_quantity, 'kind' => 'release', 'created_at' => now(),
                ]);
            }
            DB::table('sms_usage_reservations')->where('id', $reservation->id)->update(['status' => 'released', 'updated_at' => now()]);
        }, 3);
    }
}
