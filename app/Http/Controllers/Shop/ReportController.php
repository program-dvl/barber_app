<?php

namespace App\Http\Controllers\Shop;

use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\Reporting\Models\ReportExport;
use App\Domain\Reporting\Services\MetricCatalog;
use App\Domain\Reporting\Services\ReportCatalog;
use App\Domain\Reporting\Services\ReportExportService;
use App\Domain\Reporting\Services\ReportQuery;
use App\Domain\Reporting\Services\ReportService;
use App\Http\Controllers\Controller;
use App\Support\Files\TenantPrivateStorage;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports, private readonly ReportExportService $exports, private readonly TenantContext $tenancy, private readonly TenantPrivateStorage $storage) {}

    public function index(Request $request, Business $business)
    {
        $membership = $this->tenancy->membership();
        // Inventory stays out of the first operations release until its setup and
        // checkout workflow are ready to support the corresponding reports.
        $catalog = array_values(array_diff($this->reports->allowedReportKeys($membership), ['product_sales', 'stock']));
        abort_if($catalog === [], 403);
        $key = $request->filled('report')
            ? $request->string('report')->toString()
            : (in_array('overview', $catalog, true) ? 'overview' : $catalog[0]);
        abort_unless(in_array($key, $catalog, true), 403);
        $filters = $this->filters($request);
        if (empty($filters['location_ids'])) {
            $permitted = $membership->hasRole('owner', 'web') ? $business->locations() : $membership->locations();
            $branches = $permitted->orderBy('name')->get(['locations.id', 'locations.time_zone']);
            if ($branches->pluck('time_zone')->unique()->count() > 1) {
                $filters['location_ids'] = [$branches->first()->id];
            }
        }
        try {
            $result = $this->reports->run($business, $membership, $key, $filters);
        } catch (\DomainException $e) {
            throw ValidationException::withMessages(['start_date' => $e->getMessage()]);
        }
        if ($request->expectsJson()) {
            return response()->json($result);
        }

        $ownStaffFilter = (in_array($key, ['appointments', 'cancellation_no_show', 'popular_service', 'utilisation'], true) && ! $membership->hasPermissionTo(PermissionName::CalendarViewAll->value, 'web')) || (in_array($key, ['tip', 'commission', 'payroll'], true) && ! $membership->hasPermissionTo(PermissionName::CommissionsViewAll->value, 'web'));
        $locationIds = $membership->hasRole('owner', 'web') ? Location::query()->forBusiness($business)->pluck('id') : $membership->locations()->pluck('locations.id');

        return Inertia::render('Shop/Reports', [
            'catalog' => $catalog,
            'reportCatalog' => array_intersect_key(ReportCatalog::all(), array_flip($catalog)),
            'metricDefinitions' => MetricCatalog::definitions(),
            'result' => $result,
            'canExport' => $request->user()->can(PermissionName::ExportCreate->value),
            'filterOptions' => [
                'locations' => Location::query()->forBusiness($business)->whereIn('id', $locationIds)->orderBy('name')->get(['id', 'name', 'time_zone']),
                'staff' => StaffProfile::query()->forBusiness($business)->where(fn ($query) => $query->whereHas('locations', fn ($branches) => $branches->whereIn('locations.id', $locationIds))->orWhereIn('id', DB::table('sale_lines')->join('sales', 'sales.id', '=', 'sale_lines.sale_id')->where('sales.business_id', $business->id)->whereIn('sales.location_id', $locationIds)->select('sale_lines.staff_profile_id'))->orWhereIn('id', DB::table('appointment_segments')->join('appointments', 'appointments.id', '=', 'appointment_segments.appointment_id')->where('appointments.business_id', $business->id)->whereIn('appointments.location_id', $locationIds)->select('appointment_segments.staff_profile_id')))->when($ownStaffFilter, fn ($q) => $q->whereKey($membership->staffProfile?->id ?? 0))->orderBy('display_name')->get(['id', 'display_name']),
                'services' => Service::query()->forBusiness($business)->orderBy('name')->get(['id', 'name']),
                'currencies' => $this->currencies($business, $membership, $key, $filters, $locationIds),
                'methods' => $membership->hasPermissionTo(PermissionName::RevenueView->value, 'web') ? DB::table('payment_transactions')->join('sales', 'sales.id', '=', 'payment_transactions.sale_id')->where('sales.business_id', $business->id)->where('payment_transactions.business_id', $business->id)->whereIn('sales.location_id', $locationIds)->distinct()->orderBy('method')->pluck('method') : [],
                'statuses' => ['open', 'completed', 'confirmed', 'arrived', 'checked_in', 'in_service', 'cancelled_by_client', 'cancelled_by_shop', 'no_show', 'rescheduled'],
            ],
        ]);
    }

    private function currencies(Business $business, $membership, string $key, array $filters, $locationIds): array
    {
        if (in_array($key, ['tip', 'commission', 'payroll'], true)) {
            $scope = $this->reports->scope($business, $membership, $key, $filters);
            $scope['from_utc'] = CarbonImmutable::parse('1900-01-01', 'UTC');
            $scope['until_utc'] = CarbonImmutable::parse('2100-01-01', 'UTC');
            // Retain the ledger's historical location and own-staff restrictions.
            $currencies = collect();
            $codes = DB::table($key === 'tip' ? 'tip_entries' : 'commission_entries')->where('business_id', $business->id)->distinct()->pluck('currency_code');
            if ($key === 'payroll') {
                $codes = $codes->merge(DB::table('tip_entries')->where('business_id', $business->id)->distinct()->pluck('currency_code'))->unique();
            }
            foreach ($codes as $code) {
                $scope['currency_code'] = $code;
                if (DB::query()->fromSub(app(ReportQuery::class)->build($business, $key, $scope), 'ledger')->exists()) {
                    $currencies->push($code);
                }
            }
        } elseif ($membership->hasPermissionTo(PermissionName::RevenueView->value, 'web')) {
            $currencies = DB::table('sales')->where('business_id', $business->id)->whereIn('location_id', $locationIds)->distinct()->pluck('currency_code');
        } else {
            return [];
        }

        return $currencies->push($business->currency_code)->unique()->sort()->values()->all();
    }

    public function print(Request $request, Business $business)
    {
        $key = $request->string('report', 'sales')->toString();
        try {
            $result = $this->reports->run($business, $this->tenancy->membership(), $key, [...$this->filters($request), 'page' => 1, 'per_page' => 100]);
        } catch (\DomainException $e) {
            throw ValidationException::withMessages(['start_date' => $e->getMessage()]);
        }

        return view('reports.summary', [
            'business' => $business,
            'report' => $result,
            'meta' => ReportCatalog::all()[$key],
            'scopeNames' => [
                'locations' => Location::query()->forBusiness($business)->whereIn('id', $result['filters']['location_ids'])->pluck('name')->implode(', '),
                'staff' => $result['filters']['staff_ids'] ? StaffProfile::query()->forBusiness($business)->whereIn('id', $result['filters']['staff_ids'])->pluck('display_name')->implode(', ') : 'All permitted team',
                'services' => $result['filters']['service_ids'] ? Service::query()->forBusiness($business)->whereIn('id', $result['filters']['service_ids'])->pluck('name')->implode(', ') : 'All services',
            ],
            // Resolve only references already present in this permission-scoped result.
            'referenceNames' => [
                'location_id' => Location::query()->forBusiness($business)->whereIn('id', array_column($result['rows'], 'location_id'))->pluck('name', 'id')->all(),
                'staff_id' => StaffProfile::query()->forBusiness($business)->whereIn('id', array_column($result['rows'], 'staff_id'))->pluck('display_name', 'id')->all(),
                'service_id' => Service::query()->forBusiness($business)->whereIn('id', array_column($result['rows'], 'service_id'))->pluck('name', 'id')->all(),
            ],
        ]);
    }

    public function export(Request $request, Business $business)
    {
        $data = ['report' => $request->validate(['report' => ['required', 'string', 'in:'.implode(',', MetricCatalog::reportKeys())]])['report'], ...$this->filters($request)];
        $key = $data['report'];
        unset($data['report']);
        try {
            $export = $this->exports->queue($business, $this->tenancy->membership(), $key, $data);
        } catch (\DomainException $e) {
            throw ValidationException::withMessages(['start_date' => $e->getMessage()]);
        }

        if ($request->header('X-Inertia')) {
            return back()->with('success', 'Report export queued.');
        }

        return response()->json(['export' => $export->only(['public_id', 'status', 'row_count', 'completed_at'])], 202);
    }

    public function exportStatus(Request $request, Business $business, ReportExport $reportExport)
    {
        abort_unless($reportExport->business_id === $business->id && $reportExport->requested_by_membership_id === $this->tenancy->membership()->id, 404);

        abort_unless($request->user()->can(PermissionName::ExportCreate->value), 403);
        $this->assertExportScope($business, $reportExport);

        return response()->json(['export' => $reportExport->fresh()->only(['public_id', 'status', 'row_count', 'completed_at'])]);
    }

    public function download(Request $request, Business $business, ReportExport $reportExport)
    {
        abort_unless($reportExport->business_id === $business->id, 404);
        abort_unless($reportExport->requested_by_membership_id === $this->tenancy->membership()->id, 404);
        abort_unless($request->user()->can(PermissionName::ExportCreate->value), 403);
        abort_unless($reportExport->status === 'completed' && $reportExport->storage_path, 409);
        $this->assertExportScope($business, $reportExport);
        $stream = $this->storage->getStoredStream($business, $reportExport->storage_path);
        $filename = str_replace('_', '-', $reportExport->report_key).'-report-'.$reportExport->filters['start_date'].'-to-'.$reportExport->filters['end_date'].'.csv';

        return response()->streamDownload(function () use ($stream) {
            fpassthru($stream);
            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'ETag' => '"'.$reportExport->content_hash.'"']);
    }

    private function assertExportScope(Business $business, ReportExport $export): void
    {
        $scope = $this->reports->scope($business, $this->tenancy->membership(), $export->report_key, $export->filters);
        // Re-scoping a file cannot remove rows/columns already written into it.
        // Fail closed when an all-team export becomes own-only or finance is lost.
        abort_unless($scope['staff_ids'] === ($export->scope_snapshot['staff_ids'] ?? []), 403);
        abort_if(($export->scope_snapshot['can_finance'] ?? false) && ! $scope['can_finance'], 403);
        abort_if(($export->scope_snapshot['can_reasons'] ?? false) && ! $scope['can_reasons'], 403);
    }

    /** @return array<string,mixed> */
    private function filters(Request $request): array
    {
        return $request->validate([
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', ...($request->filled('start_date') ? ['after_or_equal:start_date'] : [])],
            'time_zone' => ['nullable', 'timezone'], 'location_ids' => ['array'], 'location_ids.*' => ['integer', 'distinct'],
            'staff_ids' => ['array'], 'staff_ids.*' => ['integer', 'distinct'], 'service_ids' => ['array'], 'service_ids.*' => ['integer', 'distinct'],
            'statuses' => ['array'], 'statuses.*' => ['string', 'in:open,completed,pending_confirmation,confirmed,arrived,checked_in,in_service,late,cancelled_by_client,cancelled_by_shop,no_show,rescheduled'],
            'currency_code' => ['nullable', 'string', 'regex:/^[A-Z]{3}$/'], 'method' => ['nullable', 'in:cash,card,upi,bank_transfer,payment_link,custom'],
            'compare' => ['nullable', 'in:previous,year,none'], 'granularity' => ['nullable', 'in:auto,hour,day,week,month'],
            'search' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'dimension' => ['nullable', 'in:staff,service,location,method,classification,status,type'], 'dimension_value' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', 'max:50'], 'direction' => ['nullable', 'in:asc,desc'],
        ]);
    }
}
