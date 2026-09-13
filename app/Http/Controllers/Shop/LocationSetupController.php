<?php

namespace App\Http\Controllers\Shop;

use App\Domain\Billing\Services\EntitlementEvaluator;
use App\Domain\BusinessConfiguration\Services\BusinessActivationManager;
use App\Domain\BusinessConfiguration\Services\OnboardingManager;
use App\Domain\BusinessConfiguration\Services\ReadinessEvaluator;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Business;
use App\Http\Controllers\Controller;
use App\Rules\E164Phone;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LocationSetupController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly BusinessActivationManager $activation,
        private readonly OnboardingManager $onboarding,
        private readonly ReadinessEvaluator $readiness,
        private readonly EntitlementEvaluator $entitlements,
    ) {}

    public function index(Business $business): Response
    {
        $this->authorizeManage();
        $this->onboarding->resume($business);

        $locationDecision = $this->entitlements->decide($business, 'locations.max', 'create', 1);

        return Inertia::render('Activation/Location', [
            'business' => $business->only(['public_id', 'name', 'address', 'phone', 'email', 'country_code', 'time_zone']),
            'locations' => $business->locations()->with('hours')->orderBy('name')->get()->map(fn ($location): array => [
                ...$location->only(['public_id', 'name', 'address', 'phone', 'email', 'time_zone', 'status', 'is_active']),
                'hours' => $location->hours->map->only(['day_of_week', 'opens_at', 'closes_at', 'sequence'])->values(),
            ]),
            'readiness' => $this->readiness->evaluate($business)->toArray(),
            'timeZones' => config('reference-data.time_zones', []),
            'locationAllowance' => [
                'can_add' => $locationDecision->allowed,
                'limit' => $locationDecision->entitledValue,
                'used' => $locationDecision->currentUsage ?? $business->locations()->where('is_active', true)->count(),
                'reason' => $locationDecision->code,
            ],
        ]);
    }

    public function store(Request $request, Business $business): RedirectResponse
    {
        $this->authorizeManage();
        $data = $request->validate([
            'location' => ['nullable', 'string'],
            'create_new' => ['nullable', 'boolean'],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:1000'],
            'time_zone' => ['required', 'timezone:all'],
            'phone' => ['nullable', 'string', 'max:32', new E164Phone],
            'email' => ['nullable', 'email', 'max:255'],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['integer', 'between:1,7'],
            'opens_at' => ['required', 'date_format:H:i'],
            'closes_at' => ['required', 'date_format:H:i', 'after:opens_at'],
        ]);
        if (filled($data['location'] ?? null)) {
            $business->locations()->where('public_id', $data['location'])->firstOrFail();
        }
        $this->activation->saveLocation($business, $this->context->membership(), $data);

        return back()->with('status', 'Location and opening hours saved.');
    }

    private function authorizeManage(): void
    {
        abort_unless($this->context->membership()?->hasPermissionTo(PermissionName::SettingsManage->value, 'web'), 403);
    }
}
