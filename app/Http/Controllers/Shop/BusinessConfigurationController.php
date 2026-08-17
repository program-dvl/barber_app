<?php

namespace App\Http\Controllers\Shop;

use App\Domain\Billing\Services\EntitlementEvaluator;
use App\Domain\BusinessConfiguration\Jobs\ProcessConfigurationImport;
use App\Domain\BusinessConfiguration\Models\ConfigurationChangePreview;
use App\Domain\BusinessConfiguration\Models\ConfigurationImport;
use App\Domain\BusinessConfiguration\Models\LocationHour;
use App\Domain\BusinessConfiguration\Models\LocationScheduleException;
use App\Domain\BusinessConfiguration\Models\PhysicalResource;
use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\BusinessConfiguration\Models\ServiceCategory;
use App\Domain\BusinessConfiguration\Models\ServiceResourceRequirement;
use App\Domain\BusinessConfiguration\Models\ServiceSegment;
use App\Domain\BusinessConfiguration\Models\StaffAvailabilityRule;
use App\Domain\BusinessConfiguration\Models\StaffServiceAssignment;
use App\Domain\BusinessConfiguration\Services\ConfigurationChangePreviewer;
use App\Domain\BusinessConfiguration\Services\ConfigurationImportService;
use App\Domain\BusinessConfiguration\Services\OnboardingManager;
use App\Domain\BusinessConfiguration\Services\ReadinessEvaluator;
use App\Domain\BusinessConfiguration\Services\StaffScheduleValidator;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Http\Controllers\Controller;
use App\Support\Audit\AuditWriter;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BusinessConfigurationController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly ReadinessEvaluator $readiness,
        private readonly OnboardingManager $onboarding,
        private readonly ConfigurationImportService $imports,
        private readonly AuditWriter $audit,
        private readonly StaffScheduleValidator $scheduleValidator,
        private readonly EntitlementEvaluator $entitlements,
        private readonly ConfigurationChangePreviewer $changePreviewer,
    ) {}

    public function show(Business $business): Response
    {
        $this->authorizePermission(PermissionName::SettingsManage);
        $session = $this->onboarding->resume($business);

        return Inertia::render('Configuration/Onboarding', [
            'business' => $business->only([
                'public_id', 'name', 'booking_slug', 'business_type', 'country_code', 'locale', 'currency_code',
                'time_zone', 'week_starts_on', 'appointment_interval_minutes', 'tax_posture', 'phone', 'email',
                'website_url', 'social_links', 'address', 'map_url', 'default_cancellation_policy', 'terms_url',
                'privacy_url', 'logo_path', 'cover_image_path', 'configuration_published_at',
                'online_booking_enabled', 'online_staff_preference', 'online_price_display', 'online_new_client_rule',
                'staff_gender_request_enabled', 'cancellation_cutoff_minutes', 'waitlist_offer_batch_size',
                'public_link_ttl_minutes', 'public_booking_policy_version',
            ]),
            'onboarding' => $session->only(['current_step', 'completed_steps', 'last_saved_at', 'previewed_at', 'published_at']),
            'readiness' => $this->readiness->evaluate($business)->toArray(),
            'locations' => $business->locations()->with(['hours', 'scheduleExceptions', 'physicalResources'])->get(),
            'services' => $business->services()->with(['segments', 'locations', 'staffAssignments', 'resourceRequirements'])->get(),
            'staff' => $business->staffProfiles()->with(['locations', 'availabilityRules', 'serviceAssignments'])->get(),
            'imports' => $business->configurationImports()->latest()->limit(10)->get(),
            'steps' => OnboardingManager::STEPS,
            'referenceData' => config('reference-data'),
        ]);
    }

    public function updateProfile(Request $request, Business $business): RedirectResponse
    {
        $this->authorizePermission(PermissionName::SettingsManage);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'booking_slug' => ['required', 'string', 'min:3', 'max:80'],
            'business_type' => ['required', 'string', 'max:64'], 'country_code' => ['required', 'string', 'size:2'],
            'locale' => ['required', 'string', 'max:16'], 'currency_code' => ['required', 'string', 'size:3'],
            'time_zone' => ['required', 'timezone:all'], 'week_starts_on' => ['required', 'integer', 'between:1,7'],
            'appointment_interval_minutes' => ['required', 'integer', Rule::in([5, 10, 15, 20, 30, 60])],
            'tax_posture' => ['required', Rule::in(['inclusive', 'exclusive', 'not_registered'])],
            'phone' => ['required', 'string', 'max:32'], 'email' => ['required', 'email'],
            'website_url' => ['nullable', 'url:http,https'], 'social_links' => ['nullable', 'array'],
            'social_links.*' => ['url:http,https'], 'address' => ['required', 'string', 'max:1000'],
            'map_url' => ['nullable', 'url:http,https'], 'default_cancellation_policy' => ['required', 'string', 'max:4000'],
            'terms_url' => ['required', 'url:http,https'], 'privacy_url' => ['required', 'url:http,https'],
        ]);
        $slug = $data['booking_slug'];
        unset($data['booking_slug']);
        $before = $business->only(array_keys($data));
        $business->update([...$data, 'country_code' => strtoupper($data['country_code']), 'currency_code' => strtoupper($data['currency_code'])]);
        $business = $this->onboarding->changeBookingSlug($business, $slug);
        $this->onboarding->saveStep($business, 'business_details');
        $this->audit->write('configuration.profile.updated', $business, target: $business, before: $before, after: $business->only(array_keys($data)));

        return back()->with('status', 'Business details saved.');
    }

    public function uploadBrandAsset(Request $request, Business $business): RedirectResponse
    {
        $this->authorizePermission(PermissionName::SettingsManage);
        $this->entitlements->authorize($business, 'branding.custom', 'use');
        $request->validate(['kind' => ['required', Rule::in(['logo', 'cover'])], 'asset' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120']]);
        $file = $request->file('asset');
        $this->onboarding->storeBrandAsset($business, $request->string('kind')->toString(), $file->get(), $file->extension());

        return back()->with('status', 'Brand asset saved privately.');
    }

    public function updatePublicBookingPolicy(Request $request, Business $business): RedirectResponse
    {
        $this->authorizePermission(PermissionName::SettingsManage);
        $data = $request->validate([
            'online_booking_enabled' => ['required', 'boolean'],
            'online_staff_preference' => ['required', Rule::in(['any_or_preferred', 'any_only', 'preferred_required'])],
            'online_price_display' => ['required', Rule::in(['service_setting', 'exact', 'from'])],
            'online_new_client_rule' => ['required', Rule::in(['allow', 'consultation_only', 'existing_only'])],
            'staff_gender_request_enabled' => ['required', 'boolean'],
            'cancellation_cutoff_minutes' => ['required', 'integer', 'between:0,43200'],
            'waitlist_offer_batch_size' => ['required', 'integer', 'between:1,10'],
            'public_link_ttl_minutes' => ['required', 'integer', 'between:15,43200'],
        ]);
        $before = $business->only(array_keys($data));
        $business->update([...$data, 'public_booking_policy_version' => max(1, (int) $business->public_booking_policy_version) + 1]);
        $this->onboarding->saveStep($business, 'booking_rules');
        $this->audit->write('configuration.public_booking_policy.updated', $business, target: $business, before: $before, after: $business->only(array_keys($data)), metadata: ['policy_version' => $business->public_booking_policy_version]);

        return back()->with('status', 'Public booking rules saved. In-progress clients will be asked to review the new policy.');
    }

    public function saveHours(Request $request, Business $business, Location $location): RedirectResponse
    {
        $this->authorizePermission(PermissionName::SettingsManage);
        $data = $request->validate([
            'windows' => ['present', 'array'], 'windows.*.day_of_week' => ['required', 'integer', 'between:1,7'],
            'windows.*.opens_at' => ['required', 'date_format:H:i'], 'windows.*.closes_at' => ['required', 'date_format:H:i', 'different:windows.*.opens_at'],
            'windows.*.sequence' => ['required', 'integer', 'min:1'],
        ]);
        $impactPreview = $this->requireImpactResolution($request, $business, $location, 'location_hours');
        DB::transaction(function () use ($business, $location, $data, $impactPreview, $request): void {
            $location->hours()->delete();
            foreach ($data['windows'] as $window) {
                LocationHour::query()->create([...$window, 'business_id' => $business->id, 'location_id' => $location->id]);
            }
            $this->applyImpactResolution($impactPreview, $request);
        });
        $this->onboarding->saveStep($business, 'hours');
        $this->audit->write('configuration.location_hours.updated', $business, target: $location, after: ['windows' => $data['windows']]);

        return back()->with('status', 'Opening hours saved.');
    }

    public function storeLocationException(Request $request, Business $business, Location $location): RedirectResponse
    {
        $this->authorizePermission(PermissionName::SettingsManage);
        $data = $request->validate([
            'kind' => ['required', Rule::in(['holiday', 'closure', 'special_hours', 'temporary_closure'])],
            'starts_on' => ['required', 'date'], 'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'opens_at' => ['nullable', 'required_if:kind,special_hours', 'date_format:H:i'],
            'closes_at' => ['nullable', 'required_if:kind,special_hours', 'date_format:H:i'],
            'name' => ['required', 'string', 'max:255'], 'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $impactPreview = $this->requireImpactResolution($request, $business, $location, $data['kind']);
        $exception = DB::transaction(function () use ($data, $business, $location, $impactPreview, $request): LocationScheduleException {
            $exception = LocationScheduleException::query()->create([...$data, 'business_id' => $business->id, 'location_id' => $location->id]);
            $this->applyImpactResolution($impactPreview, $request);

            return $exception;
        });
        $this->audit->write('configuration.location_exception.created', $business, target: $exception, after: $exception->toArray());

        return back()->with('status', 'Location exception saved.');
    }

    public function storeResource(Request $request, Business $business, Location $location): RedirectResponse
    {
        $this->authorizePermission(PermissionName::SettingsManage);
        $data = $request->validate(['type' => ['required', 'string', 'max:64'], 'name' => ['required', 'string', 'max:255'], 'quantity' => ['required', 'integer', 'between:1,65535'], 'is_active' => ['boolean']]);
        $resource = PhysicalResource::query()->create([...$data, 'business_id' => $business->id, 'location_id' => $location->id]);
        $this->audit->write('configuration.resource.created', $business, target: $resource, after: $resource->only(['type', 'name', 'quantity', 'is_active']));

        return back()->with('status', 'Resource saved.');
    }

    public function createFirstBookablePath(Request $request, Business $business): RedirectResponse
    {
        $this->authorizePermission(PermissionName::SettingsManage);
        $this->authorizePermission(PermissionName::StaffManage);
        $data = $request->validate([
            'location_name' => ['required', 'string', 'max:255'], 'location_address' => ['required', 'string', 'max:1000'],
            'time_zone' => ['required', 'timezone:all'], 'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['integer', 'between:1,7'], 'opens_at' => ['required', 'date_format:H:i'],
            'closes_at' => ['required', 'date_format:H:i', 'after:opens_at'], 'staff_name' => ['required', 'string', 'max:255'],
            'staff_email' => ['required', 'email'], 'staff_title' => ['nullable', 'string', 'max:255'],
            'service_name' => ['required', 'string', 'max:255'], 'category_name' => ['required', 'string', 'max:255'],
            'price_minor' => ['required', 'integer', 'min:0'], 'duration_minutes' => ['required', 'integer', 'between:1,1440'],
            'processing_minutes' => ['integer', 'between:0,1440'], 'cleanup_minutes' => ['integer', 'between:0,1440'],
            'tax_category' => ['nullable', 'string', 'max:64'], 'resource_name' => ['nullable', 'string', 'max:255'],
            'resource_type' => ['nullable', 'string', 'max:64'], 'resource_quantity' => ['nullable', 'integer', 'between:1,65535'],
            'required_quantity' => ['nullable', 'integer', 'between:1,65535'],
        ]);

        $locationIncrease = $business->locations()->where('name', $data['location_name'])->exists() ? 0 : 1;
        $staffIncrease = $business->staffProfiles()->where('status', 'active')->whereRaw('lower(email) = ?', [strtolower($data['staff_email'])])->exists() ? 0 : 1;
        if ($locationIncrease) {
            $this->entitlements->authorize($business, 'locations.max', 'create', 1);
        }
        if ($staffIncrease) {
            $this->entitlements->authorize($business, 'staff.max', 'create', 1);
        }

        DB::transaction(function () use ($business, $data): void {
            $location = Location::query()->firstOrCreate(
                ['business_id' => $business->id, 'name' => $data['location_name']],
                ['time_zone' => $data['time_zone'], 'address' => $data['location_address'], 'status' => 'active', 'is_active' => true],
            );
            foreach (array_unique($data['working_days']) as $day) {
                LocationHour::query()->updateOrCreate(
                    ['location_id' => $location->id, 'day_of_week' => $day, 'sequence' => 1, 'effective_from' => null],
                    ['business_id' => $business->id, 'opens_at' => $data['opens_at'], 'closes_at' => $data['closes_at']],
                );
            }
            $staff = StaffProfile::query()->updateOrCreate(
                ['business_id' => $business->id, 'email' => strtolower($data['staff_email'])],
                ['display_name' => $data['staff_name'], 'title' => $data['staff_title'] ?? null, 'status' => 'active', 'online_visible' => true],
            );
            $staff->locations()->syncWithoutDetaching([$location->id => ['business_id' => $business->id]]);
            foreach (array_unique($data['working_days']) as $day) {
                StaffAvailabilityRule::query()->updateOrCreate(
                    ['staff_profile_id' => $staff->id, 'kind' => 'working', 'day_of_week' => $day, 'sequence' => 1],
                    ['business_id' => $business->id, 'location_id' => $location->id, 'starts_at' => $data['opens_at'], 'ends_at' => $data['closes_at']],
                );
            }
            $category = ServiceCategory::query()->firstOrCreate(['business_id' => $business->id, 'name' => $data['category_name']]);
            $service = Service::query()->updateOrCreate(
                ['business_id' => $business->id, 'name' => $data['service_name']],
                [
                    'service_category_id' => $category->id, 'kind' => 'service', 'price_minor' => $data['price_minor'],
                    'currency_code' => $business->currency_code, 'tax_category' => $data['tax_category'] ?? null,
                    'tax_inclusive' => $business->tax_posture === 'inclusive', 'duration_minutes' => $data['duration_minutes'],
                    'processing_minutes' => $data['processing_minutes'] ?? 0, 'cleanup_minutes' => $data['cleanup_minutes'] ?? 0,
                    'deposit_type' => 'none', 'deposit_value' => 0, 'is_active' => true, 'online_visible' => true,
                ],
            );
            $service->locations()->syncWithoutDetaching([$location->id => ['business_id' => $business->id, 'is_eligible' => true]]);
            StaffServiceAssignment::query()->updateOrCreate(
                ['business_id' => $business->id, 'staff_profile_id' => $staff->id, 'service_id' => $service->id],
                ['is_qualified' => true, 'is_active' => true, 'online_visible' => true],
            );
            foreach ([
                ['kind' => 'active', 'minutes' => $data['duration_minutes'], 'sequence' => 1, 'staff' => true],
                ['kind' => 'processing', 'minutes' => $data['processing_minutes'] ?? 0, 'sequence' => 2, 'staff' => false],
                ['kind' => 'cleanup', 'minutes' => $data['cleanup_minutes'] ?? 0, 'sequence' => 3, 'staff' => true],
            ] as $segmentData) {
                if ($segmentData['minutes'] > 0) {
                    ServiceSegment::query()->updateOrCreate(
                        ['service_id' => $service->id, 'sequence' => $segmentData['sequence']],
                        ['business_id' => $business->id, 'kind' => $segmentData['kind'], 'duration_minutes' => $segmentData['minutes'], 'occupies_staff' => $segmentData['staff']],
                    );
                }
            }
            if (! empty($data['resource_name'])) {
                $resource = PhysicalResource::query()->updateOrCreate(
                    ['business_id' => $business->id, 'location_id' => $location->id, 'name' => $data['resource_name']],
                    ['type' => $data['resource_type'] ?: 'station', 'quantity' => $data['resource_quantity'] ?: 1, 'is_active' => true],
                );
                ServiceResourceRequirement::query()->updateOrCreate(
                    ['service_id' => $service->id, 'service_segment_id' => null, 'physical_resource_id' => $resource->id],
                    ['business_id' => $business->id, 'quantity' => $data['required_quantity'] ?: 1],
                );
            }
        });
        foreach (['hours', 'services', 'staff', 'staff_availability'] as $step) {
            $this->onboarding->saveStep($business, $step);
        }
        $this->audit->write('configuration.first_bookable_path.saved', $business, target: $business, after: ['location' => $data['location_name'], 'staff' => $data['staff_name'], 'service' => $data['service_name']]);

        return back()->with('status', 'Your first bookable service path is ready.');
    }

    public function storeService(Request $request, Business $business): RedirectResponse
    {
        $this->authorizePermission(PermissionName::SettingsManage);
        $data = $request->validate([
            'category' => ['nullable', 'string', 'max:255'], 'kind' => ['required', Rule::in(['service', 'addon'])],
            'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'],
            'price_type' => ['required', Rule::in(['fixed', 'from'])], 'price_minor' => ['required', 'integer', 'min:0'],
            'duration_minutes' => ['required', 'integer', 'min:1'], 'processing_minutes' => ['integer', 'min:0'],
            'cleanup_minutes' => ['integer', 'min:0'], 'tax_category' => ['nullable', 'string', 'max:64'],
            'deposit_type' => ['required', Rule::in(['none', 'fixed', 'percentage'])], 'deposit_value' => ['required', 'integer', 'min:0'],
            'minimum_notice_minutes' => ['integer', 'min:0'], 'maximum_advance_days' => ['integer', 'between:1,730'],
            'client_eligibility' => ['required', Rule::in(['all', 'new', 'existing'])], 'online_visible' => ['boolean'],
            'consultation_required' => ['boolean'], 'location_ids' => ['required', 'array', 'min:1'],
            'location_ids.*' => [Rule::exists('locations', 'id')->where('business_id', $business->id)],
        ]);
        if ($data['deposit_type'] !== 'none') {
            $this->entitlements->authorize($business, 'deposits.enabled', 'use');
        }
        $category = empty($data['category']) ? null : ServiceCategory::query()->firstOrCreate(['business_id' => $business->id, 'name' => $data['category']]);
        unset($data['category'], $data['location_ids']);
        $locationIds = $request->input('location_ids');
        $service = Service::query()->create([...$data, 'business_id' => $business->id, 'service_category_id' => $category?->id, 'currency_code' => $business->currency_code, 'tax_inclusive' => $business->tax_posture === 'inclusive']);
        $service->locations()->syncWithPivotValues($locationIds, ['business_id' => $business->id, 'is_eligible' => true]);
        $this->onboarding->saveStep($business, 'services');
        $this->audit->write('configuration.service.created', $business, target: $service, after: $service->toArray());

        return back()->with('status', 'Service saved.');
    }

    public function saveStaffAvailability(Request $request, Business $business, StaffProfile $staffProfile): RedirectResponse
    {
        $this->authorizePermission(PermissionName::StaffManage);
        $data = $request->validate([
            'rules' => ['present', 'array'], 'rules.*.kind' => ['required', Rule::in(['working', 'break', 'leave', 'holiday', 'sick_leave', 'temporary_change', 'personal_block'])],
            'rules.*.location_id' => ['nullable', Rule::exists('locations', 'id')->where('business_id', $business->id)],
            'rules.*.day_of_week' => ['nullable', 'integer', 'between:1,7'], 'rules.*.starts_on' => ['nullable', 'date'],
            'rules.*.ends_on' => ['nullable', 'date', 'after_or_equal:rules.*.starts_on'], 'rules.*.starts_at' => ['nullable', 'date_format:H:i'],
            'rules.*.ends_at' => ['nullable', 'date_format:H:i'], 'rules.*.sequence' => ['integer', 'min:1'], 'rules.*.reason' => ['nullable', 'string', 'max:255'],
        ]);
        $this->scheduleValidator->validate($data['rules']);
        $impactPreview = $this->requireImpactResolution($request, $business, $staffProfile, 'staff_availability');
        DB::transaction(function () use ($business, $staffProfile, $data, $impactPreview, $request): void {
            $staffProfile->availabilityRules()->delete();
            foreach ($data['rules'] as $rule) {
                StaffAvailabilityRule::query()->create([...$rule, 'business_id' => $business->id, 'staff_profile_id' => $staffProfile->id]);
            }
            $this->applyImpactResolution($impactPreview, $request);
        });
        $this->onboarding->saveStep($business, 'staff_availability');
        $this->audit->write('configuration.staff_availability.updated', $business, target: $staffProfile, after: ['rules' => $data['rules']]);

        return back()->with('status', 'Staff availability saved.');
    }

    public function previewChange(Request $request, Business $business, string $subjectType, string $subjectId): JsonResponse
    {
        $this->authorizePermission(PermissionName::SettingsManage);
        $data = $request->validate(['change_type' => ['required', 'string', 'max:48'], 'proposed_change' => ['required', 'array']]);
        $subject = match ($subjectType) {
            'location' => $business->locations()->where('public_id', $subjectId)->firstOrFail(),
            'staff' => $business->staffProfiles()->where('public_id', $subjectId)->firstOrFail(),
            'resource' => $business->physicalResources()->where('public_id', $subjectId)->firstOrFail(),
            default => abort(404),
        };
        $preview = $this->changePreviewer->preview($business, $subject, $data['change_type'], $data['proposed_change']);

        return response()->json($preview);
    }

    public function previewImport(Request $request, Business $business): JsonResponse
    {
        $this->authorizePermission(PermissionName::SettingsManage);
        $data = $request->validate(['entity_type' => ['required', Rule::in(['clients', 'staff', 'services', 'products'])], 'idempotency_key' => ['required', 'string', 'max:128'], 'source_name' => ['required', 'string', 'max:255'], 'csv' => ['required', 'string'], 'mapping' => ['required', 'array']]);
        $import = $this->imports->preview($business, $data['entity_type'], $data['idempotency_key'], $data['source_name'], $data['csv'], $data['mapping']);

        return response()->json($import);
    }

    public function importTemplate(Business $business, string $entityType): StreamedResponse
    {
        $this->authorizePermission(PermissionName::SettingsManage);
        $template = $this->imports->template($entityType);

        return response()->streamDownload(fn () => print $template, $entityType.'-import-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function commitImport(Request $request, Business $business, ConfigurationImport $configurationImport): JsonResponse
    {
        $this->authorizePermission(PermissionName::SettingsManage);
        abort_unless((int) $configurationImport->business_id === (int) $business->id, 404);
        $data = $request->validate(['duplicate_resolutions' => ['array'], 'duplicate_resolutions.*' => [Rule::in(['create', 'update', 'skip'])]]);
        $configurationImport->update(['status' => 'queued']);
        $this->audit->write('configuration.import.queued', $business, target: $configurationImport, after: [
            'entity_type' => $configurationImport->entity_type,
            'total_rows' => $configurationImport->total_rows,
            'duplicate_resolutions' => count($data['duplicate_resolutions'] ?? []),
        ]);
        ProcessConfigurationImport::dispatch($configurationImport->id, $data['duplicate_resolutions'] ?? []);
        $this->onboarding->saveStep($business, 'import');

        return response()->json($configurationImport->fresh());
    }

    public function preview(Business $business): RedirectResponse
    {
        $this->authorizePermission(PermissionName::SettingsManage);
        $this->onboarding->markPreviewed($business);

        return back()->with('status', 'Booking-page preview reviewed.');
    }

    public function publish(Business $business): RedirectResponse
    {
        $this->authorizePermission(PermissionName::SettingsManage);
        $this->onboarding->publish($business);

        return back()->with('status', 'Booking configuration published.');
    }

    private function authorizePermission(PermissionName $permission): void
    {
        abort_unless($this->context->membership()?->hasPermissionTo($permission->value, 'web'), 403);
    }

    private function requireImpactResolution(Request $request, Business $business, Model $subject, string $changeType): ?ConfigurationChangePreview
    {
        if (! $business->configuration_published_at) {
            return null;
        }
        $request->validate([
            'impact_preview_id' => ['required', 'string'],
            'impact_resolution' => ['nullable', Rule::in(['reassign', 'cancel', 'retain_exception'])],
            'impact_reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $preview = ConfigurationChangePreview::query()->forBusiness($business)
            ->where('public_id', $request->string('impact_preview_id'))->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())->where('change_type', $changeType)
            ->where('status', 'previewed')->where('expires_at', '>', now())->firstOrFail();
        if ($preview->affected_count > 0 && (! $request->filled('impact_resolution') || ! $request->filled('impact_reason'))) {
            throw ValidationException::withMessages(['impact_resolution' => 'Resolve every affected appointment or retain it with an explicit reason.']);
        }

        return $preview;
    }

    private function applyImpactResolution(?ConfigurationChangePreview $preview, Request $request): void
    {
        $preview?->update([
            'status' => 'applied',
            'resolution_note' => trim($request->string('impact_resolution').' '.$request->string('impact_reason')),
        ]);
    }
}
