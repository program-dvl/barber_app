<?php

namespace App\Domain\PlatformAccess\Services;

use App\Domain\Billing\Models\BillingPayment;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Membership;
use App\Domain\PlatformAccess\Models\PlatformAccountNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PlatformTenantQuery
{
    public function search(?string $search, int $limit = 25)
    {
        return Business::query()->with(['subscription.plan', 'onboardingSession'])
            ->when(filled($search), function (Builder $query) use ($search): void {
                $term = trim((string) $search);
                $query->where(fn (Builder $query) => $query->where('name', 'like', "%{$term}%")->orWhere('public_id', $term)->orWhere('slug', 'like', "%{$term}%"));
            })
            ->withCount(['memberships', 'locations', 'staffProfiles'])
            ->latest('id')->limit(min(max($limit, 1), 50))->get()->map(fn (Business $business) => $this->summary($business, false));
    }

    /** @return array<string,mixed> */
    public function summary(Business $business, bool $detailed = true): array
    {
        $business->loadMissing(['subscription.plan', 'subscription.invoices', 'onboardingSession']);
        $owner = $this->owner($business);
        $subscription = $business->subscription;
        $base = [
            'public_id' => $business->public_id, 'name' => $business->name, 'slug' => $business->slug,
            'status' => $business->status->value, 'created_at' => $business->created_at?->toIso8601String(),
            'owner' => $owner ? ['name' => $owner->name, 'email' => $owner->email, 'verified' => $owner->hasVerifiedEmail()] : null,
            'onboarding' => ['step' => $business->onboardingSession?->current_step, 'published_at' => $business->onboardingSession?->published_at?->toIso8601String()],
            'subscription' => $subscription ? [
                'status' => $subscription->status->value, 'plan' => $subscription->plan?->name,
                'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(), 'period_ends_at' => $subscription->current_period_ends_at?->toIso8601String(),
                'grace_ends_at' => $subscription->grace_ends_at?->toIso8601String(), 'restriction' => $subscription->restriction_level->value,
            ] : null,
            'usage' => [
                'locations' => $business->locations()->count(), 'active_staff' => $business->staffProfiles()->where('status', 'active')->count(),
                'memberships' => $business->memberships()->active()->count(),
            ],
        ];
        if (! $detailed) {
            return $base;
        }

        return [...$base,
            'invoices' => $subscription?->invoices()->latest('issued_at')->limit(10)->get()->map->only(['public_id', 'number', 'status', 'currency', 'total_minor', 'amount_paid_minor', 'issued_at'])->values()->all() ?? [],
            'failures' => [
                'subscription_payments' => BillingPayment::query()->where('business_id', $business->id)->where('status', 'failed')->count(),
                'notifications' => DB::table('communication_messages')->where('business_id', $business->id)->where('status', 'failed')->count(),
                'payment_webhooks' => DB::table('payment_provider_events')->where('business_id', $business->id)->where('processing_status', 'failed')->count(),
                'billing_webhooks' => DB::table('billing_provider_events')->where('business_id', $business->id)->where('status', 'failed')->count(),
            ],
            'activity' => [
                'appointments_30d' => DB::table('appointments')->where('business_id', $business->id)->where('created_at', '>=', now()->subDays(30))->count(),
                'sales_30d' => DB::table('sales')->where('business_id', $business->id)->where('created_at', '>=', now()->subDays(30))->count(),
                'messages_30d' => DB::table('communication_messages')->where('business_id', $business->id)->where('created_at', '>=', now()->subDays(30))->count(),
            ],
            'internal_notes' => PlatformAccountNote::query()->where('business_id', $business->id)->latest()->limit(20)->get()->map->only(['public_id', 'body', 'visibility', 'retain_until', 'created_at'])->values()->all(),
        ];
    }

    public function owner(Business $business): ?User
    {
        $roles = config('permission.table_names.roles');
        $assignments = config('permission.table_names.model_has_roles');
        $modelKey = config('permission.column_names.model_morph_key');
        $businessKey = config('permission.column_names.team_foreign_key');
        $userId = DB::table('memberships')
            ->join($assignments, function ($join) use ($assignments, $modelKey, $businessKey, $business): void {
                $join->on("{$assignments}.{$modelKey}", '=', 'memberships.id')
                    ->where("{$assignments}.model_type", Membership::class)
                    ->where("{$assignments}.{$businessKey}", $business->id);
            })
            ->join($roles, function ($join) use ($roles, $assignments, $businessKey, $business): void {
                $join->on("{$roles}.id", '=', "{$assignments}.role_id")->where("{$roles}.{$businessKey}", $business->id);
            })
            ->where('memberships.business_id', $business->id)->where("{$roles}.name", 'owner')->value('memberships.user_id');

        return $userId ? User::query()->find($userId) : null;
    }
}
