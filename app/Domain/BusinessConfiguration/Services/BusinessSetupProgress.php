<?php

namespace App\Domain\BusinessConfiguration\Services;

use App\Domain\Billing\Services\EntitlementEvaluator;
use App\Domain\PlatformAccess\Models\Business;

class BusinessSetupProgress
{
    public function __construct(
        private readonly ReadinessEvaluator $readiness,
        private readonly EntitlementEvaluator $entitlements,
    ) {}

    /** @return array<string, mixed> */
    public function for(Business $business): array
    {
        $result = $this->readiness->evaluate($business);
        $codes = collect($result->blockers)->pluck('code');

        $required = [
            $this->task('profile', 'Business profile', 'Identity, contact and regional details', ! $codes->contains(fn (string $code) => str_starts_with($code, 'profile.')), route('business.configuration.show', ['business' => $business, 'section' => 'business_details'])),
            $this->task('location', 'Location & hours', 'Where and when clients can visit', ! $codes->contains(fn (string $code) => str_starts_with($code, 'locations.')), route('business.locations.index', $business)),
            $this->task('services', 'Service menu', 'What clients can book', ! $codes->contains(fn (string $code) => str_starts_with($code, 'services.')), route('business.services.index', $business)),
            $this->task('team', 'Team & availability', 'Who delivers each appointment', ! $codes->contains(fn (string $code) => str_starts_with($code, 'staff.')), route('business.team.index', $business)),
            $this->task('booking_page', 'Booking page', 'Previewed and ready to share', filled($business->configuration_published_at), route('business.configuration.show', ['business' => $business, 'section' => 'preview'])),
        ];

        $requiredComplete = collect($required)->where('complete', true)->count();
        $recommended = array_values(array_filter([
            $this->entitlements->value($business, 'branding.custom')
                ? $this->task('branding', 'Add your brand', 'Logo, cover image and accent colour', filled($business->logo_path) && filled($business->cover_image_path), route('business.configuration.show', ['business' => $business, 'section' => 'business_details']))
                : null,
            $this->task('team_growth', 'Invite your team', 'Add people now or whenever you are ready', $business->staffProfiles()->where('status', 'active')->count() > 1, route('business.team.index', $business)),
            $this->task('booking_preferences', 'Fine-tune booking rules', 'Deposits, cancellation and staff choice', in_array('booking_rules', $business->onboardingSession?->completed_steps ?? [], true), route('business.configuration.show', ['business' => $business, 'section' => 'booking_rules'])),
        ]));

        return [
            'label' => $requiredComplete === count($required) ? 'Ready to take bookings' : 'Getting started',
            'percent' => (int) round(($requiredComplete / count($required)) * 100),
            'completed' => $requiredComplete,
            'total' => count($required),
            'required_complete' => $requiredComplete === count($required),
            'required' => $required,
            'recommended' => $recommended,
            'next' => collect($required)->firstWhere('complete', false) ?? collect($recommended)->firstWhere('complete', false),
        ];
    }

    /** @return array{id:string,label:string,description:string,complete:bool,href:string} */
    private function task(string $id, string $label, string $description, bool $complete, string $href): array
    {
        return compact('id', 'label', 'description', 'complete', 'href');
    }
}
