<?php

namespace App\Domain\Marketplace\LocalGrowth\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Live hard gates are deliberately outside the cached public facts. */
class PublicOfferQuery
{
    public function query(): Builder
    {
        return DB::table('marketplace_public_offers as o')
            ->join('businesses as b', 'b.id', '=', 'o.business_id')
            ->join('locations as l', fn ($j) => $j->on('l.id', '=', 'o.location_id')->on('l.business_id', '=', 'o.business_id'))
            ->join('services as s', fn ($j) => $j->on('s.id', '=', 'o.service_id')->on('s.business_id', '=', 'o.business_id'))
            ->join('marketplace_projection_work as w', 'w.business_id', '=', 'o.business_id')
            ->whereColumn('w.generation', 'w.projected_generation')->whereColumn('o.generation', 'w.generation')
            ->where('o.valid_until', '>', now())->where('b.status', 'active')->whereNull('b.closed_at')->whereNull('b.suspended_at')
            ->whereNotNull('b.configuration_published_at')->where('b.online_booking_enabled', true)->whereNotNull('b.booking_slug')
            ->where(fn ($q) => $q->where('b.marketplace_listed', true)->orWhereNull('b.marketplace_consented_at'))
            ->where('l.is_active', true)->where('l.status', 'active')->where(fn ($q) => $q->whereColumn('l.address', 'o.address')->orWhere(fn ($n) => $n->whereNull('l.address')->whereNull('o.address')))
            ->where('s.kind', 'service')->where('s.is_active', true)->where('s.online_visible', true)
            ->where(fn ($q) => $q->whereNull('s.effective_from')->orWhere('s.effective_from', '<=', now()))
            ->where(fn ($q) => $q->whereNull('s.effective_until')->orWhere('s.effective_until', '>', now()))
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('location_service as ls')
                ->whereColumn('ls.business_id', 'o.business_id')->whereColumn('ls.location_id', 'o.location_id')->whereColumn('ls.service_id', 'o.service_id')->where('ls.is_eligible', true))
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('staff_service_assignments as a')
                ->join('staff_profiles as p', fn ($j) => $j->on('p.id', '=', 'a.staff_profile_id')->on('p.business_id', '=', 'a.business_id'))
                ->join('location_staff_profile as lp', fn ($j) => $j->on('lp.staff_profile_id', '=', 'p.id')->on('lp.business_id', '=', 'p.business_id'))
                ->whereColumn('a.business_id', 'o.business_id')->whereColumn('a.service_id', 'o.service_id')->whereColumn('lp.location_id', 'o.location_id')
                ->where('a.is_active', true)->where('a.is_qualified', true)->where('a.online_visible', true)->where('p.status', 'active')->where('p.online_visible', true)
                ->where(fn ($q) => $q->whereNull('a.effective_from')->orWhere('a.effective_from', '<=', now()))
                ->where(fn ($q) => $q->whereNull('a.effective_until')->orWhere('a.effective_until', '>', now())))
            ->whereNotExists(fn ($demo) => $demo->selectRaw('1')->from('onboarding_sessions as demo')->whereColumn('demo.business_id', 'o.business_id')->where('demo.generated_data->source', 'pitch_demo'))
            ->whereNotExists(fn ($p) => $p->selectRaw('1')->from('marketplace_seo_pages as withdrawn')->whereColumn('withdrawn.business_id', 'o.business_id')->where('withdrawn.kind', 'profile')->whereIn('withdrawn.status', ['paused', 'withdrawn']))
            ->select('o.*');
    }

    public function term(Builder $q, string $kind, string $term): Builder
    {
        return $q->whereExists(fn ($terms) => $terms->selectRaw('1')->from('marketplace_offer_terms as t')->whereColumn('t.offer_id', 'o.id')->where('t.kind', $kind)->where('t.term', $term));
    }

    public function geography(Builder $q, int $id): Builder
    {
        return $q->where('o.geography_id', $id)->whereExists(fn ($g) => $g->selectRaw('1')->from('marketplace_location_geographies as lg')
            ->whereColumn('lg.business_id', 'o.business_id')->whereColumn('lg.location_id', 'o.location_id')->whereColumn('lg.geography_id', 'o.geography_id'));
    }
}
