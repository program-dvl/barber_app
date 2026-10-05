<?php

namespace App\Http\Controllers\Shop;

use App\Domain\BusinessConfiguration\Models\ConfigurationChangePreview;
use App\Domain\BusinessConfiguration\Models\LocationHour;
use App\Domain\BusinessConfiguration\Services\BusinessSetupAccess;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Domain\SchedulingOperations\Models\CapacityHold;
use App\Http\Controllers\Controller;
use App\Support\Audit\AuditWriter;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SetupHoursController extends Controller
{
    public function revision(Location $location): string
    {
        return hash('sha256', json_encode([$location->only(['time_zone', 'status', 'is_active']), $location->hours->sortBy(fn ($h) => $h->day_of_week * 100 + $h->sequence)->values()->map->only(['id', 'day_of_week', 'opens_at', 'closes_at', 'sequence', 'effective_from', 'effective_until'])->all()], JSON_THROW_ON_ERROR));
    }

    public function review(Request $request, Business $business, Location $location)
    {
        $this->authorizeSetup($business, $location);
        $data = $this->validateHours($request);
        $this->assertRevision($location, $data['revision']);
        $visits = $this->visits($business, $location);
        $preview = ConfigurationChangePreview::query()->create([
            'business_id' => $business->id, 'subject_type' => $location->getMorphClass(), 'subject_id' => $location->id,
            'change_type' => 'setup_opening_hours', 'proposed_change' => [...$data, 'impact_signature' => $this->signature($visits)],
            'affected_appointment_ids' => $visits->pluck('public_id')->all(), 'affected_count' => $visits->count(),
            'status' => 'previewed', 'expires_at' => now()->addMinutes(15),
        ]);

        return response()->json(['public_id' => $preview->public_id, 'count' => $visits->count(),
            'appointments' => $visits->take(8)->map->only(['public_id', 'starts_at_utc'])->values(), 'expires_at' => $preview->expires_at->toIso8601String()]);
    }

    public function save(Request $request, Business $business, Location $location)
    {
        $this->authorizeSetup($business, $location);
        $data = $this->validateHours($request);
        $request->validate(['preview_id' => ['required', 'string'], 'reason' => ['nullable', 'string', 'max:1000']]);
        DB::transaction(function () use ($request, $business, $location, $data): void {
            $business = Business::query()->lockForUpdate()->findOrFail($business->id);
            $location = $business->locations()->whereKey($location->id)->lockForUpdate()->firstOrFail();
            $preview = ConfigurationChangePreview::query()->where('business_id', $business->id)->where('subject_type', $location->getMorphClass())
                ->where('subject_id', $location->id)->where('change_type', 'setup_opening_hours')->where('public_id', $request->input('preview_id'))->lockForUpdate()->firstOrFail();
            if ($preview->proposed_change['windows'] !== $data['windows'] || $preview->proposed_change['revision'] !== $data['revision']) {
                throw ValidationException::withMessages(['preview' => 'Your hours changed after review. Review this schedule again.']);
            }
            if ($preview->status === 'applied') {
                return;
            }
            $this->assertRevision($location, $data['revision']);
            $visits = $this->visits($business, $location);
            if ($preview->expires_at->lte(now()) || $this->signature($visits) !== $preview->proposed_change['impact_signature']) {
                throw ValidationException::withMessages(['preview' => 'Appointments or the review changed. Review this schedule again.']);
            }
            if (CapacityHold::query()->where('business_id', $business->id)->where('location_id', $location->id)->where('status', 'active')->where('expires_at', '>', now())->exists()) {
                throw ValidationException::withMessages(['preview' => 'A client is finishing a booking. Wait for their hold to finish, then review again.']);
            }
            if ($visits->isNotEmpty() && trim($request->input('reason', '')) === '') {
                throw ValidationException::withMessages(['reason' => 'Confirm you reviewed the existing appointments. They will keep their booked times.']);
            }
            if ($location->hours()->where(fn ($q) => $q->whereNotNull('effective_from')->orWhereNotNull('effective_until'))->exists()) {
                throw ValidationException::withMessages(['windows' => 'Dated opening hours are already configured. Resolve them before replacing the regular week.']);
            }
            $before = $location->hours()->get()->map->only(['day_of_week', 'opens_at', 'closes_at', 'sequence'])->all();
            $location->hours()->delete();
            foreach ($data['windows'] as $window) {
                LocationHour::query()->create([...$window, 'business_id' => $business->id, 'location_id' => $location->id]);
            }
            $preview->update(['status' => 'applied', 'resolution_note' => $request->input('reason') ?: 'No existing appointments.']);
            app(AuditWriter::class)->write('configuration.location_hours.updated', $business, target: $location,
                before: ['windows' => $before], after: ['windows' => $data['windows']], reason: $request->input('reason'), metadata: ['retained_appointments' => $preview->affected_appointment_ids]);
        }, 3);

        return back()->with('status', 'Business hours saved. Staff working hours stay separately editable.');
    }

    private function authorizeSetup(Business $business, Location $location): void
    {
        abort_unless((int) $location->business_id === (int) $business->id, 404);
        abort_unless(app(BusinessSetupAccess::class)->allows($business, app(TenantContext::class)->membership()), 403);
    }

    private function validateHours(Request $request): array
    {
        $data = $request->validate([
            'revision' => ['required', 'string', 'size:64'], 'windows' => ['present', 'array', 'max:70'],
            'windows.*.day_of_week' => ['required', 'integer', 'between:1,7'], 'windows.*.sequence' => ['required', 'integer', 'between:1,10'],
            'windows.*.opens_at' => ['required', 'date_format:H:i'], 'windows.*.closes_at' => ['required', 'date_format:H:i'],
        ]);
        $data['windows'] = collect($data['windows'])->map(fn ($w) => ['day_of_week' => (int) $w['day_of_week'], 'opens_at' => $w['opens_at'], 'closes_at' => $w['closes_at'], 'sequence' => (int) $w['sequence']])->sortBy(fn ($w) => $w['day_of_week'] * 100 + $w['sequence'])->values()->all();
        foreach ($data['windows'] as $i => $window) {
            if ($window['opens_at'] >= $window['closes_at']) {
                throw ValidationException::withMessages(['windows.'.$i.'.closes_at' => 'Choose an end time after the start time.']);
            }
            foreach (array_slice($data['windows'], $i + 1) as $other) {
                if ($window['day_of_week'] === $other['day_of_week'] && ($window['sequence'] === $other['sequence'] || ($window['opens_at'] < $other['closes_at'] && $other['opens_at'] < $window['closes_at']))) {
                    throw ValidationException::withMessages(['windows' => 'Opening periods on the same day must not overlap.']);
                }
            }
        }

        return $data;
    }

    private function assertRevision(Location $location, string $revision): void
    {
        if (! hash_equals($this->revision($location), $revision)) {
            throw ValidationException::withMessages(['revision' => 'These hours changed elsewhere. Reload the saved week before editing.']);
        }
    }

    private function visits(Business $business, Location $location)
    {
        return Appointment::query()->where('business_id', $business->id)->where('location_id', $location->id)
            ->whereIn('status', ['pending_confirmation', 'confirmed', 'arrived', 'checked_in', 'in_service', 'late'])
            ->where(fn ($q) => $q->where('ends_at_utc', '>', now())->orWhere('status', 'in_service'))->orderBy('id')
            ->get(['public_id', 'version', 'starts_at_utc']);
    }

    private function signature($visits): string
    {
        return hash('sha256', json_encode($visits->map->only(['public_id', 'version', 'starts_at_utc'])->all(), JSON_THROW_ON_ERROR));
    }
}
