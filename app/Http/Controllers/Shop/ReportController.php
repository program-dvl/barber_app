<?php

namespace App\Http\Controllers\Shop;

use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\Reporting\Models\ReportExport;
use App\Domain\Reporting\Services\MetricCatalog;
use App\Domain\Reporting\Services\ReportExportService;
use App\Domain\Reporting\Services\ReportService;
use App\Http\Controllers\Controller;
use App\Support\Files\TenantPrivateStorage;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports, private readonly ReportExportService $exports, private readonly TenantContext $tenancy, private readonly TenantPrivateStorage $storage) {}

    public function index(Request $request, Business $business)
    {
        $membership = $this->tenancy->membership();
        $key = $request->string('report', 'sales')->toString();
        $filters = $this->filters($request);
        $result = $this->reports->run($business, $membership, $key, $filters);
        if ($request->expectsJson()) {
            return response()->json($result);
        }

        $locationIds = $membership->hasRole('owner', 'web') ? Location::query()->forBusiness($business)->pluck('id') : $membership->locations()->pluck('locations.id');

        return Inertia::render('Shop/Reports', [
            'catalog' => MetricCatalog::reportKeys(),
            'metricDefinitions' => MetricCatalog::definitions(),
            'result' => $result,
            'canExport' => $request->user()->can(PermissionName::ExportCreate->value),
            'filterOptions' => [
                'locations' => Location::query()->forBusiness($business)->whereIn('id', $locationIds)->orderBy('name')->get(['id', 'name', 'time_zone']),
                'staff' => StaffProfile::query()->forBusiness($business)->whereHas('locations', fn ($query) => $query->whereIn('locations.id', $locationIds))->orderBy('display_name')->get(['id', 'display_name']),
                'services' => Service::query()->forBusiness($business)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
                'statuses' => ['open', 'completed', 'confirmed', 'arrived', 'checked_in', 'in_service', 'cancelled_by_client', 'cancelled_by_shop', 'no_show', 'rescheduled'],
            ],
        ]);
    }

    public function print(Request $request, Business $business)
    {
        $key = $request->string('report', 'sales')->toString();
        $result = $this->reports->run($business, $this->tenancy->membership(), $key, $this->filters($request));

        return view('reports.summary', ['business' => $business, 'report' => $result]);
    }

    public function export(Request $request, Business $business)
    {
        $data = $request->validate(['report' => ['required', 'string'], 'start_date' => ['nullable', 'date'], 'end_date' => ['nullable', 'date'], 'time_zone' => ['nullable', 'timezone'], 'location_ids' => ['array'], 'location_ids.*' => ['integer'], 'staff_ids' => ['array'], 'staff_ids.*' => ['integer'], 'service_ids' => ['array'], 'service_ids.*' => ['integer'], 'statuses' => ['array'], 'statuses.*' => ['string']]);
        $key = $data['report'];
        unset($data['report']);
        $export = $this->exports->queue($business, $this->tenancy->membership(), $key, $data);

        if ($request->header('X-Inertia')) {
            return back()->with('success', 'Report export queued.');
        }

        return response()->json(['export' => $export], 202);
    }

    public function exportStatus(Request $request, Business $business, ReportExport $reportExport)
    {
        abort_unless($reportExport->business_id === $business->id && $reportExport->requested_by_membership_id === $this->tenancy->membership()->id, 404);

        return response()->json(['export' => $reportExport->fresh()]);
    }

    public function download(Request $request, Business $business, ReportExport $reportExport)
    {
        abort_unless($reportExport->business_id === $business->id, 404);
        abort_unless($reportExport->requested_by_membership_id === $this->tenancy->membership()->id, 404);
        abort_unless($request->user()->can(PermissionName::ExportCreate->value), 403);
        abort_unless($reportExport->status === 'completed' && $reportExport->storage_path, 409);
        $contents = $this->storage->getStoredKey($business, $reportExport->storage_path);

        return response($contents, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="report-'.$reportExport->public_id.'.csv"', 'ETag' => '"'.$reportExport->content_hash.'"']);
    }

    /** @return array<string,mixed> */
    private function filters(Request $request): array
    {
        return $request->validate(['start_date' => ['nullable', 'date'], 'end_date' => ['nullable', 'date'], 'time_zone' => ['nullable', 'timezone'], 'location_ids' => ['array'], 'location_ids.*' => ['integer'], 'staff_ids' => ['array'], 'staff_ids.*' => ['integer'], 'service_ids' => ['array'], 'service_ids.*' => ['integer'], 'statuses' => ['array'], 'statuses.*' => ['string']]);
    }
}
