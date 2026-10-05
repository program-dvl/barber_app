<?php

namespace App\Http\Controllers\Shop;

use App\Domain\Billing\Services\EntitlementEvaluator;
use App\Domain\BusinessConfiguration\Models\StaffServiceAssignment;
use App\Domain\BusinessConfiguration\Services\StaffWorkforceManager;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Http\Controllers\Controller;
use App\Rules\E164Phone;
use App\Support\Audit\AuditWriter;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StaffOperationsController extends Controller
{
    public function __construct(private readonly TenantContext $context, private readonly StaffWorkforceManager $schedules, private readonly AuditWriter $audit) {}

    public function preview(Request $request, Business $business, StaffProfile $staffProfile)
    {
        $this->authorizeStaff($business, $staffProfile);
        $data = $request->validate(['rules' => ['present', 'array'], 'revision' => ['required', 'string', 'size:64']]);

        return response()->json($this->schedules->preview($staffProfile, $data['rules'], $data['revision']));
    }

    public function schedule(Request $request, Business $business, StaffProfile $staffProfile)
    {
        $this->authorizeStaff($business, $staffProfile);
        $data = $request->validate(['rules' => ['present', 'array'], 'revision' => ['required', 'string', 'size:64'],
            'impact_preview_id' => ['required', 'string'], 'impact_reason' => ['nullable', 'string', 'max:1000']]);
        $this->schedules->save($staffProfile, $data['rules'], $data['revision'], $data['impact_preview_id'], $data['impact_reason'] ?? '', $request->user());

        return back()->with('status', 'Working schedule saved. Calendar and walk-ins use these hours.');
    }

    public function profile(Request $request, Business $business, StaffProfile $staffProfile, EntitlementEvaluator $entitlements)
    {
        $this->authorizeStaff($business, $staffProfile);
        $data = $request->validate([
            'display_name' => ['required', 'string', 'max:255'], 'title' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'], 'mobile' => ['nullable', new E164Phone], 'biography' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['active', 'inactive'])], 'online_visible' => ['required', 'boolean'],
            'location_ids' => ['required', 'array', 'min:1'], 'location_ids.*' => ['required', 'string', 'distinct'],
            'profile_revision' => ['required', 'string', 'size:64'], 'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $locations = $business->locations()->where('is_active', true)->whereIn('public_id', $data['location_ids'])->get();
        abort_unless($locations->count() === count($data['location_ids']), 404);
        $this->authorizeLocations($locations->pluck('id')->all());
        DB::transaction(function () use ($business, $staffProfile, $data, $locations, $request, $entitlements): void {
            Business::query()->lockForUpdate()->findOrFail($business->id);
            $business->locations()->orderBy('id')->sharedLock()->get();
            $staff = StaffProfile::query()->where('business_id', $business->id)->lockForUpdate()->findOrFail($staffProfile->id);
            $staff->load(['locations', 'availabilityRules']);
            $this->authorizeStaff($business, $staff);
            if (! hash_equals($this->schedules->profileRevision($staff), $data['profile_revision'])) {
                throw ValidationException::withMessages(['profile_revision' => 'This profile changed. Close and reopen it before editing.']);
            }
            if ($staff->membership_id && strtolower($data['email'] ?? '') !== strtolower($staff->email ?? '')) {
                throw ValidationException::withMessages(['email' => 'Linked login email changes belong in Profile & security.']);
            }
            if (filled($data['email'] ?? null) && $business->staffProfiles()->whereKeyNot($staff->id)->whereRaw('lower(email) = ?', [strtolower($data['email'])])->exists()) {
                throw ValidationException::withMessages(['email' => 'Another team member already uses this email.']);
            }
            $removed = $staff->locations->pluck('id')->diff($locations->pluck('id'));
            if ($staff->availabilityRules->whereIn('location_id', $removed)->contains(fn ($r) => ! $r->ends_on || $r->ends_on->toDateString() >= CarbonImmutable::today($staff->locations->firstWhere('id', $r->location_id)?->time_zone ?: $business->time_zone)->toDateString())) {
                throw ValidationException::withMessages(['location_ids' => 'Remove working hours and exceptions at this branch before removing its assignment.']);
            }
            if ($this->activeHolds($staff)->when($data['status'] === 'active', fn ($q) => $q->whereIn('h.location_id', $removed))->exists()) {
                throw ValidationException::withMessages(['status' => 'A live booking holds this staff member. Wait for completion or expiry before removing bookability or a branch.']);
            }
            $future = $this->futureWork($staff)->when($data['status'] === 'active', fn ($q) => $q->whereIn('a.location_id', $removed))->distinct()->count('a.id');
            if ($future && trim($data['reason'] ?? '') === '') {
                throw ValidationException::withMessages(['reason' => $future.' active appointment'.($future === 1 ? '' : 's').' affected. Review Calendar or give a reason to retain them as exceptions.']);
            }
            if ($staff->status !== 'active' && $data['status'] === 'active') {
                $entitlements->authorize($business, 'staff.max', 'create', 1);
            }
            $before = [...$staff->only(['display_name', 'title', 'email', 'mobile', 'biography', 'status', 'online_visible']), 'locations' => $staff->locations->pluck('public_id')->all()];
            $staff->fill(array_diff_key($data, array_flip(['location_ids', 'profile_revision', 'reason'])))->save();
            $staff->locations()->syncWithPivotValues($locations->pluck('id')->all(), ['business_id' => $business->id]);
            $this->audit->write('staff.profile.updated', $business, $request->user(), $staff, $data['reason'] ?? 'Staff profile updated.', $before,
                [...$staff->only(['display_name', 'title', 'email', 'mobile', 'biography', 'status', 'online_visible']), 'locations' => $data['location_ids']]);
        }, 3);

        return back()->with('status', 'Team member updated. Login access is managed separately.');
    }

    public function services(Request $request, Business $business, StaffProfile $staffProfile)
    {
        $this->authorizeStaff($business, $staffProfile);
        $data = $request->validate(['assignments' => ['present', 'array', 'max:200'],
            'assignments.*.service' => ['required', 'string', 'distinct'], 'assignments.*.is_qualified' => ['required', 'boolean'],
            'assignments.*.online_visible' => ['required', 'boolean'], 'assignments.*.duration_minutes' => ['nullable', 'integer', 'between:1,1440'],
            'assignments.*.price_minor' => ['nullable', 'integer', 'between:0,100000000'],
            'services_revision' => ['required', 'string', 'size:64'], 'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        DB::transaction(function () use ($business, $staffProfile, $data, $request): void {
            $staffProfile->locations()->orderBy('locations.id')->sharedLock()->get();
            $services = $business->services()->whereIn('public_id', array_column($data['assignments'], 'service'))->with('locations')->orderBy('id')->sharedLock()->get();
            abort_unless($services->count() === count($data['assignments']), 404);
            $staff = StaffProfile::query()->where('business_id', $business->id)->lockForUpdate()->findOrFail($staffProfile->id);
            $staff->load(['locations', 'serviceAssignments']);
            $this->authorizeStaff($business, $staff);
            if (! hash_equals($this->schedules->servicesRevision($staff), $data['services_revision'])) {
                throw ValidationException::withMessages(['services_revision' => 'Service capabilities changed. Close and reopen the profile to load the latest version.']);
            }
            $current = $staff->serviceAssignments->filter(fn ($a) => $a->is_active && (! $a->effective_from || $a->effective_from->lte(now())) && (! $a->effective_until || $a->effective_until->gt(now())))->sortByDesc('effective_from')->unique('service_id');
            $proposedQualified = collect($data['assignments'])->where('is_qualified', true)->pluck('service');
            $removed = $current->where('is_qualified', true)->filter(fn ($a) => ! $proposedQualified->contains($services->firstWhere('id', $a->service_id)?->public_id))->pluck('service_id');
            if ($removed->isNotEmpty() && $this->futureWork($staff)->join('appointment_service_lines as l', 'l.id', '=', 's.appointment_service_line_id')->whereIn('l.service_id', $removed)->exists() && trim($data['reason'] ?? '') === '') {
                throw ValidationException::withMessages(['reason' => 'Existing appointments use a capability being removed. Review them in Calendar or give a reason to retain their original service snapshots.']);
            }
            if ($removed->isNotEmpty() && $this->activeHolds($staff)->join('capacity_hold_lines as l', 'l.id', '=', 's.capacity_hold_line_id')->whereIn('l.service_id', $removed)->exists()) {
                throw ValidationException::withMessages(['assignments' => 'A live booking holds a capability being removed. Wait for completion or expiry before changing it.']);
            }
            // This editor changes current capabilities only. Future-effective variants need their own reviewed workflow.
            if ($staff->serviceAssignments->contains(fn ($a) => $a->is_active && $a->effective_from?->gt(now()))) {
                throw ValidationException::withMessages(['assignments' => 'Future service changes are already scheduled. Resolve those dated configurations before editing current capabilities.']);
            }
            foreach ($data['assignments'] as $r) {
                $service = $services->firstWhere('public_id', $r['service']);
                $old = $current->firstWhere('service_id', $service->id);
                if ($r['is_qualified'] && ! $old?->is_qualified && (! $service->is_active || $service->locations->filter(fn ($l) => $l->pivot->is_eligible && $staff->locations->contains('id', $l->id))->isEmpty())) {
                    throw ValidationException::withMessages(['assignments' => 'New qualifications require an active service offered at an assigned branch.']);
                }
                $values = ['is_qualified' => $r['is_qualified'], 'online_visible' => $r['online_visible'], 'duration_minutes' => $r['duration_minutes'] ?? null, 'price_minor' => $r['price_minor'] ?? null];
                if ($old && $old->only(array_keys($values)) == $values) {
                    continue;
                }
                $staff->serviceAssignments()->where('service_id', $service->id)->where('is_active', true)->where(fn ($q) => $q->whereNull('effective_until')->orWhere('effective_until', '>', now()))->update(['is_active' => false, 'effective_until' => now()]);
                StaffServiceAssignment::query()->create([...($old?->only(['processing_minutes', 'cleanup_minutes', 'commission_rate']) ?? []), ...$values,
                    'business_id' => $business->id, 'staff_profile_id' => $staff->id, 'service_id' => $service->id, 'is_active' => true, 'effective_from' => now()]);
            }
            // Omitted services are disabled, never deleted, and historical snapshots are untouched.
            $staff->serviceAssignments()->whereNotIn('service_id', $services->pluck('id'))->where('is_active', true)->update(['is_active' => false, 'effective_until' => now()]);
            $this->audit->write('staff.services.updated', $business, $request->user(), $staff, $data['reason'] ?? 'Service capabilities updated.',
                ['assignments' => $current->map->only(['service_id', 'is_qualified', 'duration_minutes', 'price_minor', 'online_visible'])->values()->all()], ['assignments' => $data['assignments']]);
        }, 3);

        return back()->with('status', 'Service capabilities saved. Existing visits retain their recorded duration and price.');
    }

    private function authorizeStaff(Business $business, StaffProfile $staff): void
    {
        abort_unless($staff->business_id === $business->id, 404);
        abort_unless($this->context->membership()?->hasPermissionTo(PermissionName::StaffManage->value, 'web'), 403);
        $staff->loadMissing(['locations', 'availabilityRules']);
        $this->authorizeLocations($staff->locations->pluck('id')->all());
    }

    private function authorizeLocations(array $ids): void
    {
        $actor = $this->context->membership();
        abort_unless($actor->hasRole('owner', 'web') || collect($ids)->diff($actor->locations()->pluck('locations.id'))->isEmpty(), 403);
    }

    private function activeHolds(StaffProfile $staff)
    {
        return DB::table('capacity_hold_segments as s')->join('capacity_holds as h', 'h.id', '=', 's.capacity_hold_id')
            ->where('s.business_id', $staff->business_id)->where('h.business_id', $staff->business_id)->where('s.staff_profile_id', $staff->id)
            ->where('s.occupies_staff', true)->where('h.status', 'active')->where('h.expires_at', '>', now()->utc());
    }

    private function futureWork(StaffProfile $staff)
    {
        return DB::table('appointment_segments as s')->join('appointments as a', 'a.id', '=', 's.appointment_id')
            ->where('s.business_id', $staff->business_id)->where('a.business_id', $staff->business_id)->where('s.staff_profile_id', $staff->id)
            ->whereIn('a.status', ['pending_confirmation', 'confirmed', 'arrived', 'checked_in', 'in_service', 'late'])->where(fn ($q) => $q->where('a.ends_at_utc', '>', now()->utc())->orWhere('a.status', 'in_service'));
    }
}
