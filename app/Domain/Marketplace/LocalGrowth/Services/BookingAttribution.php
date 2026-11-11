<?php

namespace App\Domain\Marketplace\LocalGrowth\Services;

use App\Domain\PublicBooking\Models\PublicBookingFlow;
use App\Domain\SchedulingOperations\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingAttribution
{
    public function privacyAllows(Request $request): bool
    {
        return $request->header('Sec-GPC') !== '1' && $request->header('DNT') !== '1';
    }

    public function observe(Request $request, string $page, string $source, string $ownerSource): void
    {
        if (! $this->privacyAllows($request)) {
            $request->session()->forget('marketplace_acquisition');

            return;
        }
        $old = $request->session()->get('marketplace_acquisition');
        $now = now()->utc();
        if (! $old || CarbonImmutable::parse($old['expires_at'])->lte($now)) {
            $old = ['first_source' => $source === 'direct' ? 'unknown' : $source, 'first_page' => $page, 'observed_at' => $now->toIso8601String(), 'expires_at' => $now->addDays(config('marketplace-growth.attribution_days'))->toIso8601String()];
        }
        if (($old['first_source'] ?? 'unknown') === 'unknown' && $source !== 'direct') {
            $old['first_source'] = $source;
            $old['first_page'] = $page;
        }
        $last = $source === 'direct' ? ($old['last_source'] ?? 'unknown') : $source;
        $ownerSource = $ownerSource === 'unknown' ? ($old['owner_source'] ?? 'unknown') : $ownerSource;
        $request->session()->put('marketplace_acquisition', [...$old, 'last_source' => $last, 'last_page' => $source === 'direct' ? ($old['last_page'] ?? $page) : $page, 'owner_source' => $ownerSource, 'consent_version' => 'mpg-consent-v1']);
    }

    public function started(PublicBookingFlow $flow, Request $request, string $entry): void
    {
        if (! config('marketplace-growth.enabled')) {
            return;
        }
        $observation = $this->privacyAllows($request) ? $request->session()->get('marketplace_acquisition') : null;
        $known = $request->header('X-Marketplace-Analytics-Consent') === '1' && $observation && CarbonImmutable::parse($observation['expires_at'])->gt(now()) && ($observation['consent_version'] ?? null) === 'mpg-consent-v1';
        DB::table('marketplace_booking_attributions')->insertOrIgnore([
            'business_id' => $flow->business_id, 'flow_id' => $flow->id, 'policy_version' => config('marketplace-growth.attribution_version'), 'entry' => $entry,
            'first_source' => $known ? $observation['first_source'] : 'unknown', 'last_source' => $known ? $observation['last_source'] : 'unknown',
            'owner_source' => $known ? $observation['owner_source'] : 'unknown', 'confidence' => $known ? 'consented_observation' : 'unknown',
            'evidence' => $known ? json_encode(collect($observation)->only(['first_page', 'last_page', 'consent_version', 'observed_at', 'expires_at'])->all(), JSON_THROW_ON_ERROR) : null,
            'observed_at' => $known ? CarbonImmutable::parse($observation['observed_at']) : null,
            'expires_at' => $known ? CarbonImmutable::parse($observation['observed_at'])->addDays(config('marketplace-growth.retention_days')) : null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Called inside the canonical confirmation transaction, including exact replay. */
    public function confirmed(PublicBookingFlow $flow, Appointment $appointment): void
    {
        if (! config('marketplace-growth.enabled')) {
            return;
        }
        abort_unless((int) $flow->business_id === (int) $appointment->business_id, 404);
        DB::table('marketplace_booking_attributions')->where('business_id', $flow->business_id)->where('flow_id', $flow->id)->whereNull('appointment_id')->where('observed_at', '<=', now()->subDays(config('marketplace-growth.attribution_days')))->update($this->unknown());
        DB::table('marketplace_booking_attributions')->where('business_id', $flow->business_id)->where('flow_id', $flow->id)->whereNull('appointment_id')->update([
            'appointment_id' => $appointment->id, 'root_appointment_id' => $appointment->id, 'confirmed_at' => $flow->confirmed_at ?? now(), 'updated_at' => now(),
        ]);
        if (! $appointment->marketplace_attribution_root_id) {
            $appointment->forceFill(['marketplace_attribution_root_id' => $appointment->id])->save();
        }
    }

    public function forget(Request $request): void
    {
        $request->session()->forget('marketplace_acquisition');
        // Withdraw optional evidence from this session's flows, including confirmed
        // flows. Necessary appointment, booking-entry and ledger facts remain.
        $ids = $request->session()->get('marketplace_attribution_flows', []);
        if ($ids) {
            DB::table('marketplace_booking_attributions')->whereIn('flow_id', $ids)->update($this->unknown());
        }
        $request->session()->forget('marketplace_attribution_flows');
    }

    public function unknown(): array
    {
        return ['first_source' => 'unknown', 'last_source' => 'unknown', 'owner_source' => 'unknown', 'confidence' => 'unknown', 'evidence' => null, 'observed_at' => null, 'expires_at' => null, 'updated_at' => now()];
    }
}
