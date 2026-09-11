<?php

namespace App\Http\Controllers\Shop;

use App\Domain\BusinessConfiguration\Services\BusinessActivationManager;
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

class TeamManagementController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly BusinessActivationManager $activation,
        private readonly ReadinessEvaluator $readiness,
    ) {}

    public function index(Business $business): Response
    {
        $this->authorizeManage();

        return Inertia::render('Team/Index', [
            'business' => $business->only(['public_id', 'name', 'country_code', 'time_zone']),
            'locations' => $business->locations()->where('is_active', true)->with('hours')->orderBy('name')->get()->map(fn ($location): array => [
                ...$location->only(['public_id', 'name', 'time_zone']),
                'hours' => $location->hours->map->only(['day_of_week', 'opens_at', 'closes_at'])->values(),
            ]),
            'services' => $business->services()->where('kind', 'service')->where('is_active', true)->orderBy('name')->get(['id', 'public_id', 'name']),
            'staff' => $business->staffProfiles()->with(['locations', 'availabilityRules', 'serviceAssignments.service'])->orderBy('display_name')->get()->map(fn ($staff): array => [
                ...$staff->only(['public_id', 'display_name', 'email', 'mobile', 'title', 'status', 'online_visible', 'membership_id']),
                'has_login' => filled($staff->membership_id),
                'locations' => $staff->locations->map->only(['public_id', 'name'])->values(),
                'availability' => $staff->availabilityRules->map->only(['kind', 'location_id', 'day_of_week', 'starts_at', 'ends_at'])->values(),
                'services' => $staff->serviceAssignments->where('is_active', true)->map(fn ($assignment): array => [
                    'public_id' => $assignment->service?->public_id,
                    'name' => $assignment->service?->name,
                ])->filter(fn (array $service): bool => filled($service['public_id']))->values(),
            ]),
            'readiness' => $this->readiness->evaluate($business)->toArray(),
        ]);
    }

    public function store(Request $request, Business $business): RedirectResponse
    {
        $this->authorizeManage();
        $data = $request->validate([
            'display_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:32', new E164Phone],
            'title' => ['nullable', 'string', 'max:255'],
            'online_visible' => ['required', 'boolean'],
            'location' => ['required', 'string'],
            'service_ids' => ['array'],
            'service_ids.*' => ['string'],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['integer', 'between:1,7'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
        ]);
        $location = $business->locations()->where('public_id', $data['location'])->where('is_active', true)->firstOrFail();
        $serviceIds = $business->services()->whereIn('public_id', $data['service_ids'] ?? [])->pluck('id')->map(fn ($id): int => (int) $id)->all();
        abort_unless(count($serviceIds) === count(array_unique($data['service_ids'] ?? [])), 404);
        $this->activation->saveProvider($business, [
            ...$data,
            'location_id' => $location->getKey(),
            'service_ids' => $serviceIds,
        ]);

        return back()->with('status', 'Provider and weekly availability saved. Login access can be invited separately.');
    }

    private function authorizeManage(): void
    {
        abort_unless($this->context->membership()?->hasPermissionTo(PermissionName::StaffManage->value, 'web'), 403);
    }
}
