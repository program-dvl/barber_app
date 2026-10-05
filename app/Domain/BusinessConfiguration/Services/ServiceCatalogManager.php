<?php

namespace App\Domain\BusinessConfiguration\Services;

use App\Domain\Billing\Services\EntitlementEvaluator;
use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\BusinessConfiguration\Models\ServiceCategory;
use App\Domain\BusinessConfiguration\Models\ServiceResourceRequirement;
use App\Domain\BusinessConfiguration\Models\ServiceSegment;
use App\Domain\BusinessConfiguration\Models\StaffServiceAssignment;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\SchedulingOperations\Models\AppointmentServiceLine;
use App\Support\Audit\AuditWriter;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Current catalogue commands. Booking and sale snapshots are never rewritten. */
class ServiceCatalogManager
{
    public const RELATIONS = ['category', 'locations', 'staffAssignments.staffProfile.locations', 'segments', 'resourceRequirements.resource', 'addons'];

    public const FIELDS = ['kind', 'name', 'description', 'price_type', 'price_minor', 'duration_minutes', 'processing_minutes', 'cleanup_minutes', 'minimum_notice_minutes', 'maximum_advance_days', 'deposit_type', 'deposit_value', 'client_eligibility', 'consultation_required', 'online_visible', 'tax_category', 'is_active'];

    public function __construct(private readonly AuditWriter $audit, private readonly EntitlementEvaluator $entitlements, private readonly TenantContext $context) {}

    public function currentAssignments(Service $service)
    {
        return $service->staffAssignments->filter(fn ($a) => $a->is_active && (! $a->effective_from || $a->effective_from->lte(now())) && (! $a->effective_until || $a->effective_until->gt(now())))
            ->sortByDesc('effective_from')->unique('staff_profile_id')->values();
    }

    public function revision(Service $service): string
    {
        $service->loadMissing(self::RELATIONS);

        return hash('sha256', json_encode([
            $service->only([...self::FIELDS, 'service_category_id', 'currency_code', 'tax_inclusive', 'effective_from', 'effective_until']),
            $service->locations->sortBy('id')->map(fn ($l) => [$l->id, $l->pivot->is_eligible, $l->pivot->price_minor])->values()->all(),
            $service->staffAssignments->sortBy('id')->map->only(['id', 'staff_profile_id', 'is_active', 'is_qualified', 'online_visible', 'price_minor', 'duration_minutes', 'processing_minutes', 'cleanup_minutes', 'commission_rate', 'effective_from', 'effective_until'])->values()->all(),
            $service->segments->map->only(['id', 'kind', 'sequence', 'duration_minutes', 'occupies_staff'])->all(),
            $service->addons->pluck('id')->sort()->values()->all(),
            $service->resourceRequirements->sortBy('id')->map->only(['id', 'physical_resource_id', 'service_segment_id', 'quantity'])->values()->all(),
        ], JSON_THROW_ON_ERROR));
    }

    public function upcoming(Business $business)
    {
        return AppointmentServiceLine::query()->where('appointment_service_lines.business_id', $business->id)
            ->join('appointments as a', 'a.id', '=', 'appointment_service_lines.appointment_id')
            ->where('a.business_id', $business->id)->whereIn('a.status', ['pending_confirmation', 'confirmed', 'arrived', 'checked_in', 'in_service', 'late'])
            ->where(fn ($q) => $q->where('a.ends_at_utc', '>', now()->utc())->orWhere('a.status', 'in_service'))
            ->orderBy('a.id')->get(['appointment_service_lines.service_id', 'a.id as appointment_id', 'a.public_id', 'a.version', 'a.starts_at_utc', 'a.location_id'])->groupBy('service_id');
    }

    public function impactRevision($visits): string
    {
        return hash('sha256', json_encode($visits->unique('appointment_id')->map(fn ($a) => [$a->appointment_id, $a->version])->values()->all(), JSON_THROW_ON_ERROR));
    }

    public function categoryRevision(ServiceCategory $category): string
    {
        return hash('sha256', json_encode(['public_id' => $category->public_id, 'name' => $category->name, 'display_order' => (int) ($category->display_order ?? 0), 'is_active' => $category->is_active ?? true], JSON_THROW_ON_ERROR));
    }

    public function authorizeLocations(array $ids): void
    {
        $member = $this->context->membership();
        abort_unless($member && ($member->hasRole('owner', 'web') || collect($ids)->diff($member->locations()->pluck('locations.id'))->isEmpty()), 403);
    }

    public function authorizeService(Service $service): void
    {
        $service->loadMissing(self::RELATIONS);
        $this->authorizeLocations($service->locations->pluck('id')->all());
        foreach ($this->currentAssignments($service)->where('is_qualified', true) as $assignment) {
            if ($assignment->staffProfile) {
                $this->authorizeLocations($assignment->staffProfile->locations->pluck('id')->all());
            }
        }
    }

    public function save(Business $business, array $data, ?Service $subject = null): Service
    {
        return DB::transaction(function () use ($business, $data, $subject): Service {
            $business = Business::query()->lockForUpdate()->findOrFail($business->id);
            $commandHash = hash('sha256', json_encode([$this->context->membership()?->id, $subject?->id, $data], JSON_THROW_ON_ERROR));
            if (isset($data['command_key'])) {
                $replay = DB::table('service_catalog_commands')->where('business_id', $business->id)->where('command_key', $data['command_key'])->first();
                if ($replay) {
                    if (! hash_equals($replay->request_hash, $commandHash)) {
                        $this->fail('command_key', 'This save key was used with different changes. Reopen the editor before saving again.');
                    }
                    $saved = $business->services()->findOrFail($replay->service_id)->load(self::RELATIONS);
                    $this->authorizeService($saved);

                    return $saved;
                }
            }
            // Match booking's location -> service -> staff lock order.
            $business->locations()->orderBy('id')->sharedLock()->get();
            $service = $subject ? $business->services()->whereKey($subject->id)->lockForUpdate()->firstOrFail()->load(self::RELATIONS) : new Service(['business_id' => $business->id]);
            $duplicateSource = filled($data['duplicate_of'] ?? null) && ! $subject ? $business->services()->where('public_id', $data['duplicate_of'])->sharedLock()->firstOrFail()->load(self::RELATIONS) : null;
            if ($duplicateSource) {
                $this->authorizeService($duplicateSource);
            }
            if ($service->exists) {
                $this->authorizeService($service);
                if (! hash_equals($this->revision($service), $data['revision'] ?? '')) {
                    $this->fail('revision', 'This service changed. Close and reopen it to load the latest configuration.');
                }
                if ($service->effective_from?->gt(now()) || $service->effective_until || $service->staffAssignments->contains(fn ($a) => $a->is_active && $a->effective_from?->gt(now()))) {
                    $this->fail('revision', 'Dated service changes are already scheduled. Resolve those configurations before editing the current service.');
                }
            }
            $locations = $business->locations()->whereIn('public_id', $data['location_ids'])->get();
            abort_unless($locations->count() === count($data['location_ids']), 404);
            $this->authorizeLocations($locations->pluck('id')->all());
            foreach ($locations as $location) {
                if ((! $location->is_active || $location->status !== 'active') && ! ($service->exists && $service->locations->contains('id', $location->id)) && ! ($duplicateSource && $duplicateSource->locations->contains('id', $location->id))) {
                    $this->fail('location_ids', 'Activate this branch in Locations before adding service availability.');
                }
            }
            $lockStaffIds = $business->staffProfiles()->whereIn('public_id', $data['staff_ids'])->pluck('id')->merge($service->exists ? $service->staffAssignments->pluck('staff_profile_id') : [])->unique()->sort()->values();
            $business->staffProfiles()->whereIn('id', $lockStaffIds)->orderBy('id')->lockForUpdate()->get();
            if ($service->exists) {
                $service->load('staffAssignments.staffProfile.locations');
                if (! hash_equals($this->revision($service), $data['revision'] ?? '')) {
                    $this->fail('revision', 'Service capabilities changed during editing. Close and reopen this service.');
                }
            }
            $staff = $business->staffProfiles()->whereIn('public_id', $data['staff_ids'])->with('locations')->orderBy('id')->get();
            abort_unless($staff->count() === count($data['staff_ids']), 404);
            foreach ($staff as $person) {
                $existingAssignment = $service->exists ? $this->currentAssignments($service)->firstWhere('staff_profile_id', $person->id) : null;
                $copiedAssignment = $duplicateSource ? $this->currentAssignments($duplicateSource)->firstWhere('staff_profile_id', $person->id) : null;
                if ($person->status !== 'active' && ! $existingAssignment?->is_qualified && ! $copiedAssignment?->is_qualified) {
                    $this->fail('staff_ids', 'Activate this team member before assigning a new service capability.');
                }
                $this->authorizeLocations($person->locations->pluck('id')->all());
                if ($person->locations->pluck('id')->intersect($locations->pluck('id'))->isEmpty()) {
                    $this->fail('staff_ids', $person->display_name.' does not work at a selected location. Choose an assigned branch or another team member.');
                }
            }
            if (($data['deposit_type'] ?? 'none') !== 'none') {
                $this->entitlements->authorize($business, 'deposits.enabled', 'use');
            }
            $category = ServiceCategory::query()->where('business_id', $business->id)->whereRaw('lower(name) = ?', [mb_strtolower(trim($data['category'] ?? ''))])->first();
            if ($category && ! $category->is_active && ($data['is_active'] ?? true)) {
                $this->fail('category', 'Restore this category before adding an active service.');
            }
            if (! $category && filled($data['category'] ?? null)) {
                $category = ServiceCategory::query()->create(['business_id' => $business->id, 'name' => trim($data['category'] ?? '')]);
            }
            $data['name'] = trim($data['name']);
            if ($business->services()->when($service->exists, fn ($q) => $q->whereKeyNot($service->id))->whereRaw('lower(name) = ?', [mb_strtolower($data['name'])])->exists()) {
                $this->fail('name', 'A service already uses this name. Open it to edit, or choose a different name.');
            }
            $before = $service->exists ? $this->auditValues($service) : [];
            $values = array_intersect_key($data, array_flip(self::FIELDS));
            // Existing clients of this command can omit status/kind without reactivating/changing them.
            $values['kind'] ??= $service->kind ?: 'service';
            $values['is_active'] ??= $service->exists ? $service->is_active : true;
            if ($service->exists && $values['kind'] !== $service->kind) {
                $this->fail('kind', 'Service and add-on identities cannot be converted after creation. Duplicate into the correct type instead.');
            }
            $current = $service->exists ? $this->currentAssignments($service) : collect();
            $assignments = collect($data['staff_overrides'] ?? [])->keyBy('staff');
            $branchPrices = collect($data['location_overrides'] ?? [])->keyBy('location');
            $qualifiedIds = $staff->pluck('id');
            $variantChanged = false;
            $changed = ! $service->exists || $service->only(array_keys($values)) != $values || $service->service_category_id !== $category?->id
                || $service->locations->filter(fn ($l) => $l->pivot->is_eligible)->pluck('id')->sort()->values()->all() !== $locations->pluck('id')->sort()->values()->all();
            foreach ($staff as $person) {
                $old = $current->firstWhere('staff_profile_id', $person->id);
                $override = $assignments->get($person->public_id);
                if (! $old?->is_qualified || ($override && $old->only(array_keys(array_diff_key($override, ['staff' => true]))) !== array_diff_key($override, ['staff' => true]))) {
                    $changed = true;
                    $variantChanged = true;
                }
            }
            if ($current->where('is_qualified', true)->pluck('staff_profile_id')->diff($qualifiedIds)->isNotEmpty()) {
                $changed = true;
            }
            foreach ($locations as $location) {
                if ($branchPrices->has($location->public_id) && $service->exists && $service->locations->firstWhere('id', $location->id)?->pivot->price_minor !== $branchPrices[$location->public_id]['price_minor']) {
                    $changed = true;
                    $variantChanged = true;
                }
            }
            $addons = $business->services()->where('kind', 'addon')->whereIn('public_id', $data['addon_ids'] ?? [])->orderBy('id')->sharedLock()->get();
            abort_unless($addons->count() === count($data['addon_ids'] ?? []), 404);
            foreach ($addons as $addon) {
                $this->authorizeService($addon);
            }
            if ($values['kind'] === 'addon' && $addons->isNotEmpty()) {
                $this->fail('addon_ids', 'An add-on cannot contain another add-on.');
            }
            if ($service->exists && array_key_exists('addon_ids', $data) && $service->addons->pluck('id')->sort()->values()->all() !== $addons->pluck('id')->sort()->values()->all()) {
                $changed = true;
            }
            if ($service->exists && $changed) {
                $this->guardHolds($business, $service);
                $visits = $this->upcoming($business)->get($service->id, collect());
                if (isset($data['impact_revision']) && ! hash_equals($this->impactRevision($visits), $data['impact_revision'])) {
                    $this->fail('impact_revision', 'Upcoming appointments changed. Close and reopen this service to review them again.');
                }
                $highImpact = $service->only(['price_minor', 'duration_minutes', 'processing_minutes', 'cleanup_minutes', 'is_active', 'online_visible', 'deposit_type', 'deposit_value']) != array_intersect_key($values, array_flip(['price_minor', 'duration_minutes', 'processing_minutes', 'cleanup_minutes', 'is_active', 'online_visible', 'deposit_type', 'deposit_value'])) || $current->where('is_qualified', true)->pluck('staff_profile_id')->diff($qualifiedIds)->isNotEmpty() || $service->locations->pluck('id')->diff($locations->pluck('id'))->isNotEmpty();
                if ($visits->isNotEmpty() && ($highImpact || $variantChanged) && trim($data['reason'] ?? '') === '') {
                    $this->fail('reason', 'Upcoming appointments retain their recorded price and duration. Add a reason confirming you reviewed their assignments in Calendar.');
                }
            }
            $service->fill([...$values, 'service_category_id' => $category?->id, 'currency_code' => $service->currency_code ?: ($duplicateSource?->currency_code ?: $business->currency_code), 'tax_inclusive' => $service->exists ? $service->tax_inclusive : $business->tax_posture === 'inclusive'])->save();
            $service->locations()->sync($locations->mapWithKeys(fn ($l) => [$l->id => ['business_id' => $business->id, 'is_eligible' => true, 'price_minor' => $branchPrices->has($l->public_id) ? $branchPrices[$l->public_id]['price_minor'] : ($service->locations->firstWhere('id', $l->id)?->pivot->price_minor)]])->all());
            foreach ($current as $old) {
                if (! $qualifiedIds->contains($old->staff_profile_id) && $old->is_qualified) {
                    $old->update(['is_active' => false, 'effective_until' => now()]);
                }
            }
            foreach ($staff as $person) {
                $old = $current->firstWhere('staff_profile_id', $person->id);
                $variant = $assignments->get($person->public_id);
                $next = ['is_qualified' => true, 'online_visible' => $old?->online_visible ?? true, 'price_minor' => $old?->price_minor, 'duration_minutes' => $old?->duration_minutes, 'processing_minutes' => $old?->processing_minutes, 'cleanup_minutes' => $old?->cleanup_minutes, 'commission_rate' => $old?->commission_rate];
                if ($variant) {
                    $next = [...$next, ...array_intersect_key($variant, $next)];
                }
                if ($next['commission_rate'] !== null) {
                    $next['commission_rate'] = number_format((float) $next['commission_rate'], 4, '.', '');
                }
                if ($old && $old->only(array_keys($next)) === $next) {
                    continue;
                }
                if ($old) {
                    $old->update(['is_active' => false, 'effective_until' => now()]);
                }
                StaffServiceAssignment::query()->create([...$next, 'business_id' => $business->id, 'service_id' => $service->id, 'staff_profile_id' => $person->id, 'is_active' => true, 'effective_from' => now()]);
            }
            if ($duplicateSource) {
                $segmentIds = [];
                foreach ($duplicateSource->segments as $segment) {
                    $copy = ServiceSegment::query()->create([...$segment->only(['kind', 'sequence', 'duration_minutes', 'occupies_staff']), 'business_id' => $business->id, 'service_id' => $service->id]);
                    $segmentIds[$segment->id] = $copy->id;
                }
                foreach ($duplicateSource->resourceRequirements as $requirement) {
                    ServiceResourceRequirement::query()->create(['business_id' => $business->id, 'service_id' => $service->id, 'physical_resource_id' => $requirement->physical_resource_id, 'service_segment_id' => $requirement->service_segment_id ? ($segmentIds[$requirement->service_segment_id] ?? null) : null, 'quantity' => $requirement->quantity]);
                }
            }
            // Keep segment identities/occupancy and segment resource links. Effective resolver redistributes same-kind totals.
            foreach (['active' => 'duration_minutes', 'processing' => 'processing_minutes', 'cleanup' => 'cleanup_minutes'] as $kind => $field) {
                if (! $service->segments()->where('kind', $kind)->exists() && (int) $values[$field] > 0) {
                    $rank = ['active' => 0, 'processing' => 1, 'cleanup' => 2];
                    $segments = $service->segments()->get();
                    $nextKind = $segments->first(fn ($segment) => ($rank[$segment->kind] ?? 0) > $rank[$kind]);
                    $sequence = $nextKind?->sequence ?? (((int) $segments->max('sequence')) + 1);
                    // Move from the end to preserve the unique sequence key.
                    foreach ($segments->where('sequence', '>=', $sequence)->sortByDesc('sequence') as $segment) {
                        $segment->update(['sequence' => $segment->sequence + 1]);
                    }
                    ServiceSegment::query()->create(['business_id' => $business->id, 'service_id' => $service->id, 'kind' => $kind, 'sequence' => $sequence, 'duration_minutes' => $values[$field], 'occupies_staff' => $kind !== 'processing']);
                }
            }
            if (array_key_exists('addon_ids', $data)) {
                $service->addons()->syncWithPivotValues($addons->pluck('id')->all(), ['business_id' => $business->id]);
            }
            $service->refresh()->load(self::RELATIONS);
            if ($before !== $this->auditValues($service)) {
                $this->audit->write($subject ? 'service.catalog.updated' : 'service.catalog.created', $business, target: $service, reason: $data['reason'] ?? '', before: $before, after: $this->auditValues($service));
            }
            if (isset($data['command_key'])) {
                DB::table('service_catalog_commands')->insert(['business_id' => $business->id, 'command_key' => $data['command_key'], 'request_hash' => $commandHash, 'service_id' => $service->id, 'created_at' => now()]);
            }

            return $service;
        }, 3);
    }

    public function status(Business $business, Service $service, bool $active, array $data): void
    {
        DB::transaction(function () use ($business, $service, $active, $data) {
            $business->locations()->orderBy('id')->sharedLock()->get();
            $service = $business->services()->whereKey($service->id)->lockForUpdate()->firstOrFail()->load(self::RELATIONS);
            $this->authorizeService($service);
            if (! hash_equals($this->revision($service), $data['revision'] ?? '')) {
                $this->fail('revision', 'This service changed. Refresh the catalogue before changing its status.');
            }
            if ($active === $service->is_active) {
                return;
            }
            if ($service->effective_from?->gt(now()) || $service->effective_until) {
                $this->fail('revision', 'Resolve the dated service configuration before changing its status.');
            }
            if ($active && $service->category && ! $service->category->is_active) {
                $this->fail('category', 'Restore the category before activating this service.');
            }
            $this->guardHolds($business, $service);
            $visits = $this->upcoming($business)->get($service->id, collect());
            if (! hash_equals($this->impactRevision($visits), $data['impact_revision'] ?? '')) {
                $this->fail('impact_revision', 'Upcoming appointments changed. Refresh and review again.');
            }
            if (! $active && $visits->isNotEmpty() && trim($data['reason'] ?? '') === '') {
                $this->fail('reason', 'Add a reason to retain upcoming appointments with their original service snapshots.');
            }
            $before = $service->only(['is_active', 'online_visible']);
            $service->update(['is_active' => $active]);
            $this->audit->write('activation.service.status_changed', $business, target: $service, reason: $data['reason'] ?? '', before: $before, after: $service->only(['is_active', 'online_visible']));
        }, 3);
    }

    private function guardHolds(Business $business, Service $service): void
    {
        if (DB::table('capacity_hold_lines as l')->join('capacity_holds as h', 'h.id', '=', 'l.capacity_hold_id')->where('l.business_id', $business->id)->where('l.service_id', $service->id)->where('h.status', 'active')->where('h.expires_at', '>', now()->utc())->exists()) {
            $this->fail('revision', 'A client is holding this service while booking. Wait for confirmation or expiry before changing it.');
        }
    }

    private function auditValues(Service $service): array
    {
        return [...$service->only(self::FIELDS), 'currency_code' => $service->currency_code, 'staff_count' => $this->currentAssignments($service)->where('is_qualified', true)->count(), 'location_count' => $service->locations->count(), 'category' => $service->category?->name,
            'locations' => $service->locations->map(fn ($l) => ['name' => $l->name, 'price_minor' => $l->pivot->price_minor])->all(),
            'staff' => $this->currentAssignments($service)->where('is_qualified', true)->map(fn ($a) => ['name' => $a->staffProfile?->display_name, ...$a->only(['price_minor', 'duration_minutes', 'processing_minutes', 'cleanup_minutes', 'online_visible', 'commission_rate'])])->all(), 'addons' => $service->addons->pluck('name')->all()];
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
