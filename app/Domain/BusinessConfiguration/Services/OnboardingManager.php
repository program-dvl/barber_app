<?php

namespace App\Domain\BusinessConfiguration\Services;

use App\Domain\BusinessConfiguration\Models\OnboardingSession;
use App\Domain\PlatformAccess\Models\Business;
use App\Support\Audit\AuditWriter;
use App\Support\Files\TenantPrivateStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OnboardingManager
{
    public const STEPS = ['business_details', 'hours', 'services', 'staff', 'staff_availability', 'booking_rules', 'import', 'preview', 'publish'];

    public const GUIDED_STEPS = ['business_type', 'business_shape', 'location', 'starter_services'];

    public function __construct(
        private readonly ReadinessEvaluator $readiness,
        private readonly BookingSlugManager $slugs,
        private readonly TenantPrivateStorage $storage,
        private readonly AuditWriter $audit,
    ) {}

    public function resume(Business $business): OnboardingSession
    {
        return OnboardingSession::query()->firstOrCreate(
            ['business_id' => $business->id],
            [
                'schema_version' => config('business-onboarding.schema_version', 2),
                'current_step' => self::GUIDED_STEPS[0],
                'completed_steps' => [],
                'answers' => [],
                'started_at' => now(),
                'last_saved_at' => now(),
            ],
        );
    }

    /** @param array<string, mixed> $answers */
    public function saveGuidedAnswers(Business $business, string $step, array $answers): OnboardingSession
    {
        if (! in_array($step, self::GUIDED_STEPS, true)) {
            throw ValidationException::withMessages(['step' => 'Unknown guided onboarding step.']);
        }

        $session = $this->resume($business);
        if ($session->guided_completed_at) {
            return $session;
        }

        $index = array_search($step, self::GUIDED_STEPS, true);
        $session->forceFill([
            'schema_version' => config('business-onboarding.schema_version', 2),
            'answers' => [...($session->answers ?? []), ...$answers],
            'current_step' => self::GUIDED_STEPS[min($index + 1, count(self::GUIDED_STEPS) - 1)],
            'last_saved_at' => now(),
        ])->save();

        return $session->fresh();
    }

    public function saveStep(Business $business, string $step): OnboardingSession
    {
        if (! in_array($step, self::STEPS, true)) {
            throw ValidationException::withMessages(['step' => 'Unknown onboarding step.']);
        }
        $session = $this->resume($business);
        $completed = array_values(array_unique([...($session->completed_steps ?? []), $step]));
        $next = self::STEPS[min(array_search($step, self::STEPS, true) + 1, count(self::STEPS) - 1)];
        $session->update(['completed_steps' => $completed, 'current_step' => $next, 'last_saved_at' => now()]);

        return $session->fresh();
    }

    public function markPreviewed(Business $business): OnboardingSession
    {
        $session = $this->saveStep($business, 'preview');
        $session->update(['previewed_at' => now()]);

        return $session->fresh();
    }

    public function changeBookingSlug(Business $business, string $slug): Business
    {
        return $this->slugs->change($business, $slug);
    }

    public function storeBrandAsset(Business $business, string $kind, string $contents, string $extension): string
    {
        if (! in_array($kind, ['logo', 'cover'], true) || ! in_array(strtolower($extension), ['png', 'jpg', 'jpeg', 'webp'], true)) {
            throw ValidationException::withMessages(['asset' => 'Brand assets must be PNG, JPEG, or WebP logo/cover images.']);
        }
        $path = 'configuration/branding/'.$kind.'-'.hash('sha256', $contents).'.'.strtolower($extension);
        $this->storage->put($business, $path, $contents);
        $business->forceFill([$kind === 'logo' ? 'logo_path' : 'cover_image_path' => $path])->save();

        return $path;
    }

    public function publish(Business $business): Business
    {
        return DB::transaction(function () use ($business): Business {
            $business = Business::query()->lockForUpdate()->findOrFail($business->id);
            $result = $this->readiness->evaluate($business);
            if (! $result->publishable) {
                throw ValidationException::withMessages(['readiness' => array_map(fn ($item) => $item['message'], $result->blockers)]);
            }
            $before = ['configuration_published_at' => $business->configuration_published_at?->toIso8601String()];
            $business->forceFill(['configuration_published_at' => now()])->save();
            $session = $this->saveStep($business, 'publish');
            $session->update(['published_at' => now()]);
            $this->audit->write('configuration.published', $business, target: $business, before: $before, after: [
                'configuration_published_at' => $business->configuration_published_at?->toIso8601String(),
                'ready_within_30_minutes' => $session->started_at->diffInMinutes($session->published_at) <= 30,
            ]);

            return $business->fresh();
        });
    }
}
