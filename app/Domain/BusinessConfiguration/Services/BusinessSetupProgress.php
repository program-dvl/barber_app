<?php

namespace App\Domain\BusinessConfiguration\Services;

use App\Domain\Billing\Services\EntitlementEvaluator;
use App\Domain\PlatformAccess\Models\Business;

class BusinessSetupProgress
{
    public function __construct(private readonly ReadinessEvaluator $readiness, private readonly EntitlementEvaluator $entitlements) {}

    public function for(Business $business): array
    {
        $inspection = $this->readiness->inspect($business);
        $result = $inspection['operational'];
        $counts = $inspection['counts'];
        $href = fn ($section) => route('business.configuration.show', ['business' => $business, 'section' => $section]);
        $required = [];
        foreach ([
            ['profile', 'Business profile', 'Name, country, currency and time zone', 'profile.', 'business_details'],
            ['location', 'Location & opening hours', $counts['locations'].' active location'.($counts['locations'] === 1 ? '' : 's'), 'locations.', 'hours'],
            ['services', 'Review your services', $counts['services'].' active service'.($counts['services'] === 1 ? '' : 's').' · check prices and duration', 'services.', 'services'],
            ['team', 'Your team', $counts['staff'].' active team member'.($counts['staff'] === 1 ? '' : 's'), 'staff.active', 'team'],
            ['availability', 'Services & working hours', $counts['bookable_services'].' service'.($counts['bookable_services'] === 1 ? '' : 's').' with qualified staff and matching hours', 'staff.availability', 'team'],
            ['booking_preferences', 'Booking preferences', 'Your calendar interval and cancellation rules', 'rules.', 'booking_rules'],
        ] as [$id, $label, $description, $prefix, $section]) {
            $issues = collect($result->blockers)->filter(fn ($item) => str_starts_with($item['code'], $prefix));
            $required[] = [...$this->task($id, $label, $issues->first()['message'] ?? $description, $issues->isEmpty(), $href($section)), 'section' => $section, 'priority' => 'Required'];
        }
        $complete = collect($required)->where('complete', true)->count();
        $recommended = [
            [...$this->task('online_booking', 'Online booking', $business->configuration_published_at
                ? ($business->online_booking_enabled ? 'Published booking page' : 'Online bookings are paused') : 'Preview your page, then choose when to go live',
                filled($business->configuration_published_at) && $business->online_booking_enabled && $inspection['publication']->publishable, $href('preview')), 'section' => 'preview'],
            [...$this->task('notifications', 'Client notifications', 'Prepared messages · review email and SMS delivery', false, route('business.communications.page', $business)), 'section' => 'connections'],
            [...$this->task('import', 'Bring your records', 'Import clients, services or team when you are ready', $business->configurationImports()->where('status', 'completed')->exists(), $href('import')), 'section' => 'import'],
        ];
        if ($this->entitlements->value($business, 'branding.custom')) {
            $recommended[] = [...$this->task('branding', 'Make it yours', 'Add your logo or a photo of your business', filled($business->logo_path), $href('branding')), 'section' => 'branding'];
        }

        return [
            'label' => $result->publishable ? 'Ready to take bookings' : 'Getting started',
            'percent' => (int) round($complete / count($required) * 100),
            'completed' => $complete, 'total' => count($required), 'required_complete' => $result->publishable,
            'required' => $required, 'recommended' => $recommended,
            'next' => collect($required)->firstWhere('complete', false),
            'counts' => $counts, 'bookable_service_ids' => $inspection['bookable_service_ids'], 'online_service_ids' => $inspection['online_service_ids'], 'operational' => $result->toArray(), 'publication' => $inspection['publication']->toArray(),
            'online_state' => $business->configuration_published_at
                ? (! $business->online_booking_enabled ? 'Paused' : ($inspection['publication']->publishable ? 'Live' : 'Needs attention'))
                : ($inspection['publication']->publishable ? 'Ready to publish' : 'Not published'),
            'starter' => $business->onboardingSession?->generated_data ?? [],
        ];
    }

    private function task(string $id, string $label, string $description, bool $complete, string $href): array
    {
        return compact('id', 'label', 'description', 'complete', 'href');
    }
}
