<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\BillingPromotion;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubscriptionPromotions
{
    public function seasonal(): ?array
    {
        // Highest saving wins when campaigns overlap. Never stack campaigns.
        $offer = BillingPromotion::available()->whereNull('code')->orderByDesc('percentage')->orderBy('id')->first();

        return $offer ? $this->snapshot($offer) : null;
    }

    public function apply(array $quote, ?string $code = null): array
    {
        $seasonal = $this->seasonal();
        $offers = $seasonal ? [$seasonal] : [];
        if (filled($code)) {
            $coupon = BillingPromotion::available()->where('code', Str::upper(trim($code)))->first();
            if (! $coupon) {
                throw ValidationException::withMessages(['coupon_code' => 'This code is unavailable or has expired. Check the code or remove it to continue.']);
            }
            // A standalone coupon replaces the seasonal offer, never quietly adds to it.
            $offers = $coupon->can_combine ? [...$offers, $this->snapshot($coupon)] : [$this->snapshot($coupon)];
        }
        $remaining = $quote['total_minor'];
        foreach ($offers as &$offer) {
            $offer['saving_minor'] = intdiv($remaining * $offer['percentage'] + 50, 100);
            $remaining -= $offer['saving_minor'];
        }
        unset($offer);
        if ($offers && $remaining < 50) {
            throw ValidationException::withMessages(['coupon_code' => 'These offers cannot be combined for this payment. Remove the coupon to use the seasonal offer.']);
        }

        return [...$quote, 'promotions' => $offers, 'discount_minor' => $quote['total_minor'] - $remaining,
            'first_payment_minor' => $remaining];
    }

    public function assertUnchanged(array $quote): void
    {
        $code = collect($quote['promotions'] ?? [])->last(fn ($offer) => filled($offer['code']));
        $current = $this->apply($quote, $code['code'] ?? null);
        abort_unless($current['promotions'] === ($quote['promotions'] ?? [])
            && $current['first_payment_minor'] === ($quote['first_payment_minor'] ?? $quote['total_minor']),
            409, 'This offer changed. Review your subscription again to see the current total.');
    }

    private function snapshot(BillingPromotion $offer): array
    {
        return ['id' => $offer->public_id, 'name' => $offer->name, 'code' => $offer->code,
            'percentage' => $offer->percentage, 'can_combine' => $offer->can_combine,
            'ends_at' => $offer->ends_at?->toIso8601String()];
    }
}
