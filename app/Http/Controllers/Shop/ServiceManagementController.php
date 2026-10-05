<?php

namespace App\Http\Controllers\Shop;

use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\BusinessConfiguration\Models\ServiceCategory;
use App\Domain\BusinessConfiguration\Services\OnboardingManager;
use App\Domain\BusinessConfiguration\Services\ReadinessEvaluator;
use App\Domain\BusinessConfiguration\Services\ServiceCatalogManager;
use App\Domain\MoneyCommerce\Models\CommerceSetting;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Business;
use App\Http\Controllers\Controller;
use App\Support\Audit\AuditWriter;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ServiceManagementController extends Controller
{
    public function __construct(private readonly TenantContext $context, private readonly ServiceCatalogManager $catalog, private readonly ReadinessEvaluator $readiness) {}

    public function index(Business $business): Response
    {
        $this->authorizeManage();
        $member = $this->context->membership();
        $allLocations = $business->locations()->orderBy('name')->get();
        $scope = $member->hasRole('owner', 'web') ? $allLocations->pluck('id') : $member->locations()->pluck('locations.id');
        $locations = $allLocations->whereIn('id', $scope);
        $staff = $business->staffProfiles()->with(['locations', 'availabilityRules'])->orderBy('display_name')->get()
            ->filter(fn ($s) => $s->locations->pluck('id')->diff($scope)->isEmpty() && $s->locations->pluck('id')->intersect($scope)->isNotEmpty());
        $services = $business->services()->with(ServiceCatalogManager::RELATIONS)->orderByDesc('is_active')->orderBy('name')->get()
            ->filter(fn ($s) => $s->locations->pluck('id')->diff($scope)->isEmpty() && $this->catalog->currentAssignments($s)->where('is_qualified', true)->every(fn ($a) => $a->staffProfile && $a->staffProfile->locations->pluck('id')->diff($scope)->isEmpty()));
        $visits = $this->catalog->upcoming($business);
        $settings = CommerceSetting::query()->where('business_id', $business->id)->first();
        $categories = ServiceCategory::query()->where('business_id', $business->id)->orderBy('display_order')->orderBy('name')->get();

        return Inertia::render('Services/Index', [
            'business' => $business->only(['public_id', 'name', 'currency_code', 'locale', 'time_zone', 'tax_posture', 'booking_slug', 'online_booking_enabled', 'configuration_published_at']),
            'locations' => $locations->map->only(['public_id', 'name', 'is_active', 'status'])->values(),
            'staff' => $staff->map(fn ($s) => [...$s->only(['public_id', 'display_name', 'title', 'online_visible', 'status']), 'location_ids' => $s->locations->pluck('public_id')->all(), 'has_working_hours' => $s->availabilityRules->where('kind', 'working')->isNotEmpty()])->values(),
            'category_order_revision' => hash('sha256', $categories->map(fn ($c) => $this->catalog->categoryRevision($c))->implode('|')),
            'categories' => $categories->map(fn ($c) => [...$c->only(['public_id', 'name', 'display_order', 'is_active']), 'revision' => $this->catalog->categoryRevision($c), 'count' => $services->where('service_category_id', $c->id)->count()]),
            'services' => $services->map(function ($s) use ($visits, $staff, $services) {
                $current = $this->catalog->currentAssignments($s)->where('is_qualified', true);
                $eligibleLocations = $s->locations->filter(fn ($l) => $l->pivot->is_eligible && $l->is_active && $l->status === 'active');
                $available = $current->filter(fn ($a) => $staff->where('status', 'active')->contains('id', $a->staff_profile_id) && $a->staffProfile->locations->pluck('id')->intersect($eligibleLocations->pluck('id'))->isNotEmpty());
                $upcoming = $visits->get($s->id, collect())->unique('appointment_id');
                $warnings = [];
                if ($s->is_active && $eligibleLocations->isEmpty()) {
                    $warnings[] = 'No active location';
                }
                if ($s->is_active && $available->isEmpty()) {
                    $warnings[] = 'No eligible staff';
                } elseif ($s->is_active && ! $available->contains(fn ($a) => $staff->firstWhere('id', $a->staff_profile_id)?->availabilityRules->where('kind', 'working')->isNotEmpty())) {
                    $warnings[] = 'Staff hours needed';
                }
                if ($s->is_active && $s->online_visible && ! $available->contains(fn ($a) => $a->online_visible && $a->staffProfile->online_visible)) {
                    $warnings[] = 'No online staff';
                }
                if ($s->is_active && $available->isNotEmpty() && $eligibleLocations->contains(fn ($location) => ! $available->contains(fn ($a) => $a->staffProfile->locations->contains('id', $location->id)))) {
                    $warnings[] = 'Some locations have no eligible staff';
                }
                if ($s->is_active && $s->duration_minutes < 1) {
                    $warnings[] = 'Duration needed';
                }
                if ($s->is_active && $s->category && ! $s->category->is_active) {
                    $warnings[] = 'Category inactive';
                }

                return [...$s->only([...ServiceCatalogManager::FIELDS, 'public_id', 'currency_code', 'tax_inclusive', 'image_path']), 'updated_at' => $s->updated_at?->toIso8601String(),
                    'category' => $s->category?->name, 'category_id' => $s->category?->public_id, 'category_order' => $s->category?->display_order ?? 65535,
                    'revision' => $this->catalog->revision($s), 'impact_revision' => $this->catalog->impactRevision($upcoming), 'upcoming_count' => $upcoming->count(),
                    'upcoming' => $upcoming->take(5)->map->only(['public_id', 'starts_at_utc'])->values(), 'warnings' => $warnings,
                    'locations' => $s->locations->filter(fn ($l) => $l->pivot->is_eligible)->map(fn ($l) => [...$l->only(['public_id', 'name']), 'price_minor' => $l->pivot->price_minor])->values(),
                    'staff' => $current->map(fn ($a) => ['public_id' => $a->staffProfile?->public_id, 'display_name' => $a->staffProfile?->display_name, 'status' => $a->staffProfile?->status, ...$a->only(['price_minor', 'duration_minutes', 'processing_minutes', 'cleanup_minutes', 'commission_rate', 'online_visible'])])->filter(fn ($a) => filled($a['public_id']))->values(),
                    'addon_ids' => $s->addons->pluck('public_id')->all(),
                    'parent_names' => $s->kind === 'addon' ? $services->filter(fn ($parent) => $parent->addons->contains('id', $s->id))->pluck('name')->all() : [],
                    'resources' => $s->resourceRequirements->map(fn ($r) => ['name' => $r->resource?->name, 'quantity' => $r->quantity, 'segment' => $s->segments->firstWhere('id', $r->service_segment_id)?->kind])->values(),
                    'processing_releases_staff' => ! $s->segments->contains(fn ($segment) => $segment->kind === 'processing' && $segment->occupies_staff),
                    'dated_configuration' => (bool) ($s->effective_from?->gt(now()) || $s->effective_until || $s->staffAssignments->contains(fn ($a) => $a->is_active && $a->effective_from?->gt(now()))),
                ];
            })->values(),
            'commerce' => ['default_deposit_type' => $settings?->default_deposit_type ?? 'none', 'default_deposit_value' => $settings?->default_deposit_value ?? 0, 'tax_rate_bps' => $settings?->default_tax_rate_bps ?? 0, 'tax_inclusive' => (bool) ($settings?->tax_inclusive ?? false)],
            'readiness' => Inertia::optional(fn () => $this->readiness->evaluate($business)->toArray()),
        ]);
    }

    public function store(Request $request, Business $business): RedirectResponse
    {
        $this->authorizeManage();
        $this->catalog->save($business, $this->validated($request));
        app(OnboardingManager::class)->saveStep($business, 'services');

        return back()->with('status', 'Service added. Calendar, walk-ins and checkout use this catalogue.');
    }

    public function update(Request $request, Business $business, Service $service): RedirectResponse
    {
        $this->authorizeManage();
        abort_unless((int) $service->business_id === (int) $business->id, 404);
        $request->validate(['revision' => ['required', 'string', 'size:64'], 'impact_revision' => ['required', 'string', 'size:64']]);
        $this->catalog->save($business, $this->validated($request), $service);

        return back()->with('status', 'Service saved. Existing visits keep their recorded price and duration.');
    }

    public function status(Request $request, Business $business, Service $service): RedirectResponse
    {
        $this->authorizeManage();
        abort_unless((int) $service->business_id === (int) $business->id, 404);
        $data = $request->validate(['active' => ['required', 'boolean'], 'revision' => ['required', 'string', 'size:64'], 'impact_revision' => ['required', 'string', 'size:64'], 'reason' => ['nullable', 'string', 'max:1000']]);
        $this->catalog->status($business, $service, (bool) $data['active'], $data);

        return back()->with('status', $data['active'] ? 'Service activated with its saved online booking setting.' : 'Service deactivated. Appointment and sales history are preserved.');
    }

    public function category(Request $request, Business $business): RedirectResponse
    {
        $this->authorizeManage();
        $data = $request->validate(['category_id' => ['nullable', 'string'], 'name' => ['required', 'string', 'max:255'], 'is_active' => ['required', 'boolean'], 'revision' => ['nullable', 'string']]);
        DB::transaction(function () use ($business, $data): void {
            Business::query()->lockForUpdate()->findOrFail($business->id);
            $category = filled($data['category_id'] ?? null) ? ServiceCategory::query()->where('business_id', $business->id)->where('public_id', $data['category_id'])->lockForUpdate()->firstOrFail() : new ServiceCategory(['business_id' => $business->id]);
            if ($category->exists && $this->catalog->categoryRevision($category) !== ($data['revision'] ?? '')) {
                throw ValidationException::withMessages(['name' => 'This category changed. Reopen category management and try again.']);
            }
            if ($category->exists) {
                $services = $category->services()->with('locations')->get();
                foreach ($services as $service) {
                    $this->catalog->authorizeService($service);
                }
                if (! $data['is_active'] && $services->contains('is_active', true)) {
                    throw ValidationException::withMessages(['is_active' => 'Move or deactivate the active services before archiving this category.']);
                }
            }
            $name = trim($data['name']);
            if ($name === '' || ServiceCategory::query()->where('business_id', $business->id)->when($category->exists, fn ($q) => $q->whereKeyNot($category->id))->whereRaw('lower(name) = ?', [mb_strtolower($name)])->exists()) {
                throw ValidationException::withMessages(['name' => 'Use a unique, non-empty category name.']);
            }
            $before = $category->exists ? $category->only(['name', 'is_active']) : [];
            $category->fill(['name' => $name, 'is_active' => $data['is_active']])->save();
            app(AuditWriter::class)->write('service.category.saved', $business, target: $category, before: $before, after: $category->only(['name', 'is_active']));
        }, 3);

        return back()->with('status', 'Category saved.');
    }

    public function categoryOrder(Request $request, Business $business): RedirectResponse
    {
        $this->authorizeManage();
        $data = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['required', 'string', 'distinct'], 'revision' => ['required', 'string', 'size:64']]);
        DB::transaction(function () use ($business, $data): void {
            Business::query()->lockForUpdate()->findOrFail($business->id);
            $categories = ServiceCategory::query()->where('business_id', $business->id)->with('services.locations')->orderBy('display_order')->orderBy('name')->lockForUpdate()->get();
            if (! hash_equals(hash('sha256', $categories->map(fn ($c) => $this->catalog->categoryRevision($c))->implode('|')), $data['revision'])) {
                throw ValidationException::withMessages(['categories' => 'Category order changed. Reopen category management before reordering.']);
            }
            abort_unless($categories->pluck('public_id')->sort()->values()->all() === collect($data['ids'])->sort()->values()->all(), 422);
            foreach ($categories as $category) {
                foreach ($category->services as $service) {
                    $this->catalog->authorizeService($service);
                }
            }
            $before = $categories->pluck('name')->all();
            foreach ($data['ids'] as $index => $id) {
                $categories->firstWhere('public_id', $id)->update(['display_order' => $index]);
            }
            app(AuditWriter::class)->write('service.categories.reordered', $business, before: ['categories' => $before], after: ['categories' => collect($data['ids'])->map(fn ($id) => $categories->firstWhere('public_id', $id)->name)->all()]);
        }, 3);

        return back()->with('status', 'Category order saved.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'duplicate_of' => ['nullable', 'string'], 'category' => ['nullable', 'string', 'max:255'], 'kind' => ['sometimes', Rule::in(['service', 'addon'])], 'is_active' => ['sometimes', 'boolean'],
            'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:4000'],
            'price_type' => ['required', Rule::in(['fixed', 'from'])], 'price_minor' => ['required', 'integer', 'between:0,100000000'],
            'duration_minutes' => ['required', 'integer', 'between:1,1440'], 'processing_minutes' => ['required', 'integer', 'between:0,1440'], 'cleanup_minutes' => ['required', 'integer', 'between:0,1440'],
            'minimum_notice_minutes' => ['required', 'integer', 'between:0,525600'], 'maximum_advance_days' => ['required', 'integer', 'between:1,730'],
            'deposit_type' => ['required', Rule::in(['none', 'fixed', 'percentage'])], 'deposit_value' => ['required', 'integer', 'between:0,100000000'],
            'client_eligibility' => ['required', Rule::in(['all', 'new', 'existing'])], 'consultation_required' => ['required', 'boolean'], 'online_visible' => ['required', 'boolean'],
            'tax_category' => ['nullable', 'string', 'max:64'], 'location_ids' => ['present', 'array'], 'location_ids.*' => ['required', 'string', 'distinct'],
            'staff_ids' => ['present', 'array'], 'staff_ids.*' => ['required', 'string', 'distinct'],
            'staff_overrides' => ['sometimes', 'array'], 'staff_overrides.*.staff' => ['required', 'string', 'distinct'],
            'staff_overrides.*.price_minor' => ['nullable', 'integer', 'between:0,100000000'], 'staff_overrides.*.duration_minutes' => ['nullable', 'integer', 'between:1,1440'],
            'staff_overrides.*.processing_minutes' => ['nullable', 'integer', 'between:0,1440'], 'staff_overrides.*.cleanup_minutes' => ['nullable', 'integer', 'between:0,1440'],
            'staff_overrides.*.online_visible' => ['required', 'boolean'], 'staff_overrides.*.commission_rate' => ['nullable', 'numeric', 'between:0,1'],
            'location_overrides' => ['sometimes', 'array'], 'location_overrides.*.location' => ['required', 'string', 'distinct'], 'location_overrides.*.price_minor' => ['nullable', 'integer', 'between:0,100000000'],
            'addon_ids' => ['sometimes', 'array'], 'addon_ids.*' => ['required', 'string', 'distinct'],
            'command_key' => ['sometimes', 'uuid'], 'revision' => ['nullable', 'string', 'size:64'], 'impact_revision' => ['nullable', 'string', 'size:64'], 'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        foreach (['price_minor', 'duration_minutes', 'processing_minutes', 'cleanup_minutes', 'minimum_notice_minutes', 'maximum_advance_days', 'deposit_value'] as $field) {
            $data[$field] = (int) $data[$field];
        }
        foreach ($data['staff_overrides'] ?? [] as $index => $row) {
            foreach (['price_minor', 'duration_minutes', 'processing_minutes', 'cleanup_minutes'] as $field) {
                if (isset($row[$field])) {
                    $data['staff_overrides'][$index][$field] = (int) $row[$field];
                }
            }
            if (isset($row['commission_rate'])) {
                $data['staff_overrides'][$index]['commission_rate'] = number_format((float) $row['commission_rate'], 4, '.', '');
            }
        }
        foreach ($data['location_overrides'] ?? [] as $index => $row) {
            if (isset($row['price_minor'])) {
                $data['location_overrides'][$index]['price_minor'] = (int) $row['price_minor'];
            }
        }
        if (trim($data['name']) === '') {
            throw ValidationException::withMessages(['name' => 'Enter a service name.']);
        }
        if ($data['deposit_type'] === 'percentage' && $data['deposit_value'] > 10000) {
            throw ValidationException::withMessages(['deposit_value' => 'A percentage deposit cannot exceed 100%.']);
        }
        if ($data['deposit_type'] === 'fixed' && $data['deposit_value'] > $data['price_minor']) {
            throw ValidationException::withMessages(['deposit_value' => 'The fixed deposit cannot exceed the base price.']);
        }
        if (collect($data['staff_overrides'] ?? [])->pluck('staff')->diff($data['staff_ids'])->isNotEmpty()) {
            throw ValidationException::withMessages(['staff_overrides' => 'Overrides must belong to assigned staff.']);
        }
        if (collect($data['location_overrides'] ?? [])->pluck('location')->diff($data['location_ids'])->isNotEmpty()) {
            throw ValidationException::withMessages(['location_overrides' => 'Overrides must belong to available locations.']);
        }

        return $data;
    }

    private function authorizeManage(): void
    {
        abort_unless($this->context->membership()?->hasPermissionTo(PermissionName::SettingsManage->value, 'web'), 403);
    }
}
