<?php

namespace App\Domain\Marketplace\LocalGrowth\Services;

use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Membership;
use App\Domain\Reporting\Services\ReportService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class GrowthOutcomeReport
{
    public function for(Business $business, Membership $membership, array $filters): array
    {
        abort_unless((int) $membership->business_id === (int) $business->id && $membership->hasRole('owner', 'web') && $membership->hasPermissionTo(PermissionName::RevenueView->value, 'web'), 403);
        Validator::make($filters, ['start_date' => ['sometimes', 'date_format:Y-m-d'], 'end_date' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:start_date'], 'currency_code' => ['sometimes', Rule::in(array_keys(config('reference-data.currencies')))]])->validate();
        $scope = app(ReportService::class)->scope($business, $membership, 'overview', $filters);
        $roots = $this->roots($business, $scope);
        $source = "coalesce(x.first_source, 'unknown')";
        $entry = "coalesce(x.entry, 'unknown')";
        $owner = "coalesce(x.owner_source, 'unknown')";
        $last = "coalesce(x.last_source, 'unknown')";
        $q = DB::query()->fromSub($roots, 'c')->leftJoin('marketplace_booking_attributions as x', fn ($j) => $j->on('x.appointment_id', '=', 'c.root_id')->where('x.business_id', $business->id)->where(fn ($q) => $q->whereNull('x.expires_at')->orWhere('x.expires_at', '>', now())));
        $outcomes = (clone $q)->selectRaw("{$source} as source, {$last} as last_source, {$entry} as entry, {$owner} as owner_source, coalesce(x.confidence, 'unknown') as confidence")
            ->selectRaw("count(*) as confirmations, sum(case when c.status = 'completed' then 1 else 0 end) as completed_visits, sum(case when c.status in ('cancelled_by_client','cancelled_by_shop') then 1 else 0 end) as cancellations, sum(case when c.status = 'no_show' then 1 else 0 end) as no_shows, sum(c.price_minor) as booked_minor")
            ->selectRaw("count(distinct case when c.status = 'completed' and c.client_id is not null and not exists (select 1 from appointments prior where prior.business_id = ? and prior.client_id = c.client_id and prior.status = 'completed' and (prior.completed_at < c.completed_at or (prior.completed_at = c.completed_at and prior.id < c.latest_id))) then c.client_id end) as new_to_business_clients", [$business->id])
            ->groupBy('x.first_source', 'x.last_source', 'x.entry', 'x.owner_source', 'x.confidence')->orderBy('source')->get();
        // Every immutable transaction is read once. Allocation corrections move
        // attribution between visits without claiming another collection occurred.
        $ledger = DB::table('payment_transactions as p')->leftJoin('sales as sale', fn ($j) => $j->on('sale.id', '=', 'p.sale_id')->on('sale.business_id', '=', 'p.business_id'))
            ->join('appointments as pa', fn ($j) => $j->on('pa.id', '=', DB::raw('coalesce(p.appointment_id, sale.appointment_id)'))->on('pa.business_id', '=', 'p.business_id'))
            ->where('p.business_id', $business->id)->where('p.status', 'succeeded')->where('p.currency_code', $scope['currency_code']);
        $money = (clone $ledger)->joinSub($roots, 'c', fn ($j) => $j->on('c.root_id', '=', DB::raw('coalesce(pa.marketplace_attribution_root_id, pa.id)')))
            ->leftJoin('marketplace_booking_attributions as x', fn ($j) => $j->on('x.appointment_id', '=', 'c.root_id')->where('x.business_id', $business->id)->where(fn ($q) => $q->whereNull('x.expires_at')->orWhere('x.expires_at', '>', now())))
            ->whereIn('p.kind', ['payment', 'refund', 'void', 'correction'])
            ->selectRaw("{$source} as source, {$last} as last_source, {$entry} as entry, {$owner} as owner_source, coalesce(x.confidence, 'unknown') as confidence")
            ->selectRaw("sum(case when p.kind = 'payment' then p.amount_minor else 0 end) as collected_minor, sum(case when p.kind in ('refund','void') then p.amount_minor else 0 end) as refunded_minor, sum(case when p.kind = 'correction' then p.amount_minor else 0 end) as allocation_in_minor")
            ->groupBy('x.first_source', 'x.last_source', 'x.entry', 'x.owner_source', 'x.confidence')->get();
        $outgoing = DB::table('payment_transactions as correction')->join('payment_transactions as original', fn ($j) => $j->on('original.id', '=', 'correction.parent_transaction_id')->on('original.business_id', '=', 'correction.business_id'))
            ->join('appointments as pa', fn ($j) => $j->on('pa.id', '=', 'original.appointment_id')->on('pa.business_id', '=', 'original.business_id'))
            ->joinSub($roots, 'c', fn ($j) => $j->on('c.root_id', '=', DB::raw('coalesce(pa.marketplace_attribution_root_id, pa.id)')))
            ->leftJoin('marketplace_booking_attributions as x', fn ($j) => $j->on('x.appointment_id', '=', 'c.root_id')->where('x.business_id', $business->id)->where(fn ($q) => $q->whereNull('x.expires_at')->orWhere('x.expires_at', '>', now())))
            ->where('correction.business_id', $business->id)->where('correction.kind', 'correction')->where('correction.status', 'succeeded')->where('correction.currency_code', $scope['currency_code'])
            ->selectRaw("{$source} as source, {$last} as last_source, {$entry} as entry, {$owner} as owner_source, coalesce(x.confidence, 'unknown') as confidence, sum(correction.amount_minor) as allocation_out_minor")
            ->groupBy('x.first_source', 'x.last_source', 'x.entry', 'x.owner_source', 'x.confidence')->get();
        $key = fn ($r) => implode('|', [$r->source, $r->last_source, $r->entry, $r->owner_source, $r->confidence]);
        $rows = $outcomes->map(function ($r) use ($money, $outgoing, $key) {
            $m = $money->first(fn ($v) => $key($v) === $key($r));
            $o = $outgoing->first(fn ($v) => $key($v) === $key($r));

            return [...(array) $r, 'collected_minor' => (int) ($m?->collected_minor ?? 0), 'refunded_minor' => (int) ($m?->refunded_minor ?? 0), 'allocation_in_minor' => (int) ($m?->allocation_in_minor ?? 0), 'allocation_out_minor' => (int) ($o?->allocation_out_minor ?? 0), 'net_recorded_minor' => (int) ($m?->collected_minor ?? 0) - (int) ($m?->refunded_minor ?? 0) + (int) ($m?->allocation_in_minor ?? 0) - (int) ($o?->allocation_out_minor ?? 0)];
        })->all();
        $abandoned = DB::table('public_booking_flows')->where('business_id', $business->id)->whereNull('appointment_id')->where('created_at', '>=', $scope['from_utc'])->where('created_at', '<', $scope['until_utc'])->where('expires_at', '<=', now())->count();

        return ['rows' => $rows, 'unconfirmed_expired_sessions' => $abandoned, 'currency' => $scope['currency_code'], 'time_zone' => $scope['time_zone'], 'from' => $scope['from']->toDateString(), 'to' => $scope['to']->toDateString(), 'policy_version' => config('marketplace-growth.attribution_version')];
    }

    private function roots(Business $business, array $scope): Builder
    {
        return DB::table('public_booking_flows as f')->join('appointments as r', fn ($j) => $j->on('r.id', '=', 'f.appointment_id')->on('r.business_id', '=', 'f.business_id'))
            ->join('appointments as a', fn ($j) => $j->on(DB::raw('coalesce(a.marketplace_attribution_root_id, a.id)'), '=', 'r.id')->on('a.business_id', '=', 'r.business_id'))
            ->where('f.business_id', $business->id)->where('f.status', 'confirmed')->where('f.confirmed_at', '>=', $scope['from_utc'])->where('f.confirmed_at', '<', $scope['until_utc'])
            ->whereNull('a.rescheduled_to_appointment_id')->whereIn('a.location_id', $scope['location_ids'])->where('a.currency_code', $scope['currency_code'])
            ->distinct()->select('r.id as root_id', 'a.id as latest_id', 'a.status', 'a.client_id', 'a.completed_at', 'a.price_minor');
    }
}
