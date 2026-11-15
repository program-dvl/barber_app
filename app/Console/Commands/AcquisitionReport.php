<?php

namespace App\Console\Commands;

use App\Domain\Billing\Models\OwnerRegistrationIntent;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class AcquisitionReport extends Command
{
    protected $signature = 'analytics:acquisition-report {--from= : First local date, YYYY-MM-DD} {--to= : Last local date, YYYY-MM-DD} {--timezone=Asia/Kolkata : Reporting time zone}';

    protected $description = 'Read aggregate owner registrations and verified trial activations without exporting identities or sending analytics';

    public function handle(): int
    {
        $zone = $this->option('timezone');
        if (! in_array($zone, timezone_identifiers_list(), true)) {
            $this->error('Choose a valid reporting time zone.');

            return self::FAILURE;
        }
        foreach (['from', 'to'] as $option) {
            $value = $this->option($option);
            if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)
                || ! checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4))) {
                $this->error('Provide valid --from and --to dates as YYYY-MM-DD.');

                return self::FAILURE;
            }
        }
        $start = CarbonImmutable::parse($this->option('from'), $zone)->startOfDay();
        $end = CarbonImmutable::parse($this->option('to'), $zone)->startOfDay()->addDay();
        if ($end <= $start || $start->diffInDays($end) > 366) {
            $this->error('Choose an ordered range of no more than 366 days.');

            return self::FAILURE;
        }

        // Current explicit provenance only: do not guess test accounts from names or geography.
        $query = OwnerRegistrationIntent::query()->where(function (Builder $query) {
            $query->whereNull('business_id')->orWhereDoesntHave('business.onboardingSession',
                fn (Builder $session) => $session->where('generated_data->source', 'pitch_demo'));
        });
        $within = fn (Builder $query, string $column) => $query
            ->where($column, '>=', $start->utc())->where($column, '<', $end->utc());
        $trials = (clone $query)->where('status', 'completed')->whereNotNull('business_id')
            ->whereHas('user', fn (Builder $user) => $user->whereNotNull('email_verified_at'))
            ->whereHas('business.subscription', fn (Builder $subscription) => $subscription->whereNotNull('trial_started_at'));

        $this->line(json_encode([
            'definition_version' => 1,
            'from' => $this->option('from'), 'to' => $this->option('to'), 'timezone' => $zone,
            'utc_start_inclusive' => $start->utc()->toIso8601String(),
            'utc_end_exclusive' => $end->utc()->toIso8601String(),
            'owner_registrations' => $within(clone $query, 'created_at')->count(),
            'verified_trial_activations' => $within(clone $trials, 'completed_at')->distinct()->count('business_id'),
            'trial_cohort_booking_setup_published' => $within(clone $trials, 'completed_at')
                ->whereHas('business', fn (Builder $business) => $business->whereNotNull('configuration_published_at'))
                ->distinct()->count('business_id'),
            'limitations' => [
                'Separate event-date counts, not a registration-cohort conversion rate.',
                'Trial activation means completed verified owner onboarding with a saved trial subscription; not a button click, payment or first booking.',
                'Setup publication is the current state of the trial cohort, not proof of first use or publication within this date range.',
                'No channel attribution; do not join these aggregates to individual Google visitors.',
                'Only explicitly marked pitch demos are excluded; other staff/test registrations may remain.',
                'Historical account deletion or missing registration intents can limit coverage.',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
