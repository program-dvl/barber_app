<?php

namespace App\Http\Controllers\Shop;

use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\BusinessConfiguration\Services\BusinessActivationManager;
use App\Domain\BusinessConfiguration\Services\ReadinessEvaluator;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Business;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ServiceManagementController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly BusinessActivationManager $activation,
        private readonly ReadinessEvaluator $readiness,
    ) {}

    public function index(Business $business): Response
    {
        $this->authorizeManage();

        return Inertia::render('Services/Index', [
            'business' => $business->only(['public_id', 'name', 'currency_code', 'tax_posture']),
            'locations' => $business->locations()->where('is_active', true)->orderBy('name')->get(['id', 'public_id', 'name']),
            'staff' => $business->staffProfiles()->where('status', 'active')->with(['locations', 'availabilityRules'])->orderBy('display_name')->get()->map(fn ($staff): array => [
                ...$staff->only(['id', 'public_id', 'display_name', 'title', 'online_visible']),
                'location_ids' => $staff->locations->pluck('public_id')->values(),
                'has_working_hours' => $staff->availabilityRules->where('kind', 'working')->isNotEmpty(),
            ]),
            'services' => $business->services()->where('kind', 'service')->with(['category', 'locations', 'staffAssignments.staffProfile'])->orderByDesc('is_active')->orderBy('name')->get()->map(fn ($service): array => [
                ...$service->only(['public_id', 'name', 'description', 'price_type', 'price_minor', 'currency_code', 'duration_minutes', 'processing_minutes', 'cleanup_minutes', 'minimum_notice_minutes', 'maximum_advance_days', 'deposit_type', 'deposit_value', 'client_eligibility', 'consultation_required', 'online_visible', 'is_active']),
                'category' => $service->category?->name,
                'locations' => $service->locations->map->only(['public_id', 'name'])->values(),
                'staff' => $service->staffAssignments->where('is_active', true)->map(fn ($assignment): array => [
                    'public_id' => $assignment->staffProfile?->public_id,
                    'display_name' => $assignment->staffProfile?->display_name,
                ])->filter(fn (array $staff): bool => filled($staff['public_id']))->values(),
            ]),
            'readiness' => $this->readiness->evaluate($business)->toArray(),
        ]);
    }

    public function store(Request $request, Business $business): RedirectResponse
    {
        $this->authorizeManage();
        $data = $this->validated($request);
        $this->activation->saveService($business, $this->resolveAssignments($business, $data));

        return back()->with('status', 'Service saved and connected to its booking path.');
    }

    public function update(Request $request, Business $business, Service $service): RedirectResponse
    {
        $this->authorizeManage();
        abort_unless((int) $service->business_id === (int) $business->getKey(), 404);
        $data = $this->validated($request);
        $this->activation->saveService($business, [...$this->resolveAssignments($business, $data), 'service' => $service->public_id]);

        return back()->with('status', 'Service changes saved.');
    }

    public function status(Request $request, Business $business, Service $service): RedirectResponse
    {
        $this->authorizeManage();
        $data = $request->validate(['active' => ['required', 'boolean']]);
        $this->activation->setServiceActive($business, $service, (bool) $data['active']);

        return back()->with('status', $data['active'] ? 'Service restored.' : 'Service archived without deleting booking history.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'category' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:4000'],
            'price_type' => ['required', Rule::in(['fixed', 'from'])],
            'price_minor' => ['required', 'integer', 'min:0'],
            'duration_minutes' => ['required', 'integer', 'between:1,1440'],
            'processing_minutes' => ['required', 'integer', 'between:0,1440'],
            'cleanup_minutes' => ['required', 'integer', 'between:0,1440'],
            'minimum_notice_minutes' => ['required', 'integer', 'between:0,525600'],
            'maximum_advance_days' => ['required', 'integer', 'between:1,730'],
            'deposit_type' => ['required', Rule::in(['none', 'fixed', 'percentage'])],
            'deposit_value' => ['required', 'integer', 'min:0'],
            'client_eligibility' => ['required', Rule::in(['all', 'new', 'existing'])],
            'consultation_required' => ['required', 'boolean'],
            'online_visible' => ['required', 'boolean'],
            'tax_category' => ['nullable', 'string', 'max:64'],
            'location_ids' => ['required', 'array', 'min:1'],
            'location_ids.*' => ['string'],
            'staff_ids' => ['required', 'array', 'min:1'],
            'staff_ids.*' => ['string'],
        ]);
        if ($data['deposit_type'] === 'percentage' && $data['deposit_value'] > 10000) {
            throw ValidationException::withMessages(['deposit_value' => 'A percentage deposit cannot exceed 100%.']);
        }

        return $data;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function resolveAssignments(Business $business, array $data): array
    {
        $locationIds = $business->locations()->whereIn('public_id', array_unique($data['location_ids']))->where('is_active', true)->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $staffIds = $business->staffProfiles()->whereIn('public_id', array_unique($data['staff_ids']))->where('status', 'active')->pluck('id')->map(fn ($id): int => (int) $id)->all();
        abort_unless(count($locationIds) === count(array_unique($data['location_ids'])), 404);
        abort_unless(count($staffIds) === count(array_unique($data['staff_ids'])), 404);

        return [...$data, 'location_ids' => $locationIds, 'staff_ids' => $staffIds];
    }

    private function authorizeManage(): void
    {
        abort_unless($this->context->membership()?->hasPermissionTo(PermissionName::SettingsManage->value, 'web'), 403);
    }
}
