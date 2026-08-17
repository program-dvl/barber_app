<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Enums\RestrictionLevel;
use App\Domain\Billing\Enums\SubscriptionStatus;
use App\Domain\Billing\Models\BillingPlan;
use App\Domain\Billing\Models\BusinessSubscription;
use App\Domain\Billing\Models\OwnerRegistrationIntent;
use App\Domain\ClientRecords\Services\ClientFormService;
use App\Domain\PlatformAccess\Enums\BusinessStatus;
use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Membership;
use App\Domain\PlatformAccess\Services\BusinessAccessBootstrapper;
use App\Domain\PlatformAccess\Services\MembershipAccessManager;
use App\Domain\Reporting\Services\InstrumentationService;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class OwnerOnboardingService
{
    public function __construct(
        private readonly BusinessAccessBootstrapper $access,
        private readonly ClientFormService $clientForms,
        private readonly MembershipAccessManager $memberships,
        private readonly AuditWriter $audit,
        private readonly InstrumentationService $instrumentation,
    ) {}

    public function complete(User $user): ?Business
    {
        if (! $user->hasVerifiedEmail()) {
            throw new LogicException('Owner onboarding requires a verified email address.');
        }

        return DB::transaction(function () use ($user): ?Business {
            $intent = OwnerRegistrationIntent::query()->where('user_id', $user->getKey())->lockForUpdate()->first();

            if (! $intent) {
                return null;
            }

            if ($intent->business_id) {
                return Business::query()->findOrFail($intent->business_id);
            }

            $plan = BillingPlan::query()->where('is_trial_default', true)->where('is_active', true)->firstOrFail();
            $business = Business::query()->create([
                'public_id' => (string) Str::ulid(),
                'name' => $intent->business_name,
                'slug' => $this->uniqueSlug($intent->business_name),
                'status' => BusinessStatus::Active,
            ]);

            $this->access->bootstrap($business);
            $this->clientForms->seedStarterTemplates($business->id);
            $membership = Membership::query()->create([
                'public_id' => (string) Str::ulid(),
                'business_id' => $business->getKey(),
                'user_id' => $user->getKey(),
                'status' => 'active',
                'joined_at' => now(),
            ]);
            $this->memberships->assignStarterRole($membership, StarterRole::Owner, $user, 'Verified owner registration.');

            $trialStartedAt = now();
            $subscription = BusinessSubscription::query()->create([
                'business_id' => $business->getKey(),
                'billing_plan_id' => $plan->getKey(),
                'provider' => config('billing.provider'),
                'status' => SubscriptionStatus::Trialing,
                'restriction_level' => RestrictionLevel::None,
                'trial_started_at' => $trialStartedAt,
                'trial_ends_at' => $trialStartedAt->copy()->addDays(config('billing.trial_days')),
            ]);

            $intent->update(['business_id' => $business->getKey(), 'status' => 'completed', 'completed_at' => now()]);
            $this->instrumentation->record(
                $business,
                'trial.qualified_started',
                'verified-owner-registration:'.$intent->getKey(),
                ['source' => 'verified_owner_registration'],
            );
            $this->audit->write(
                action: 'subscription.trial.started',
                business: $business,
                actor: $user,
                target: $subscription,
                reason: 'Verified owner registration.',
                after: ['plan_code' => $plan->code, 'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String()],
            );

            return $business;
        }, 3);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'business';

        do {
            $slug = $base.'-'.Str::lower(Str::random(6));
        } while (Business::query()->where('slug', $slug)->exists());

        return $slug;
    }
}
