<?php

namespace App\Http\Controllers\Marketplace;

use App\Domain\Marketplace\LocalGrowth\Services\GrowthOutcomeReport;
use App\Domain\PlatformAccess\Models\Business;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class GrowthReportController extends Controller
{
    public function __invoke(Request $request, Business $business, GrowthOutcomeReport $reports)
    {
        abort_unless(config('marketplace-growth.enabled'), 404);
        $member = app(TenantContext::class)->membership();
        abort_unless($member?->hasRole('owner', 'web'), 403);
        $data = $request->validate(['location' => ['nullable', 'ulid'], 'start_date' => ['nullable', 'date_format:Y-m-d'], 'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'], 'currency_code' => ['nullable', Rule::in(array_keys(config('reference-data.currencies')))]]);
        $locations = $business->locations()->orderBy('id')->get();
        $location = isset($data['location']) ? $locations->firstWhere('public_id', $data['location']) : $locations->first();
        abort_unless($location, 404);
        $filters = [...array_filter($data, fn ($v) => $v !== null && $v !== ''), 'location_ids' => [$location->id]];
        $report = $reports->for($business, $member, $filters);
        $search = DB::table('marketplace_search_performance')->where('path', '/marketplace/salons/'.$business->booking_slug)->whereBetween('day', [$report['from'], $report['to']])->selectRaw('count(*) as records, sum(impressions) as impressions, sum(clicks) as clicks, max(updated_at) as imported_at')->first();

        return Inertia::render('Marketplace/GrowthReport', [
            'businessLabel' => $business->name, 'report' => $report,
            'filters' => ['location' => $location->public_id, 'start_date' => $report['from'], 'end_date' => $report['to'], 'currency_code' => $report['currency']],
            'locations' => $locations->map->only(['public_id', 'name'])->values(), 'currencies' => config('reference-data.currencies'),
            'searchConsole' => ['available' => $search->records > 0, 'impressions' => $search->impressions >= 3 ? (int) $search->impressions : null, 'clicks' => $search->clicks >= 3 ? (int) $search->clicks : null, 'imported_at' => $search->imported_at],
        ]);
    }
}
