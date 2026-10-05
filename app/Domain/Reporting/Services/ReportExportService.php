<?php

namespace App\Domain\Reporting\Services;

use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Membership;
use App\Domain\Reporting\Jobs\GenerateReportExport;
use App\Domain\Reporting\Models\ReportExport;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ReportExportService
{
    public function __construct(private readonly ReportService $reports) {}

    /** @param array<string,mixed> $filters */
    public function queue(Business $business, Membership $membership, string $reportKey, array $filters): ReportExport
    {
        if (! $membership->hasPermissionTo(PermissionName::ExportCreate->value, 'web')) {
            throw new AccessDeniedHttpException('Export permission is required.');
        }
        $scope = $this->reports->scope($business, $membership, $reportKey, $filters);
        $normalized = ['start_date' => $scope['from']->toDateString(), 'end_date' => $scope['to']->toDateString(), 'time_zone' => $scope['time_zone'], 'location_ids' => $scope['location_ids'], 'staff_ids' => $scope['staff_ids'], 'service_ids' => $scope['service_ids'], 'statuses' => $scope['statuses'], 'currency_code' => $scope['currency_code'], 'method' => $scope['method'], 'search' => $filters['search'] ?? '', 'compare' => 'none', 'dimension' => $filters['dimension'] ?? '', 'dimension_value' => $filters['dimension_value'] ?? ''];
        $pending = ReportExport::query()->where('business_id', $business->id)->where('requested_by_membership_id', $membership->id)->where('report_key', $reportKey)->whereIn('status', ['queued', 'processing'])->where('created_at', '>=', now()->subMinutes(5))->get()->first(fn ($export) => $export->filters === $normalized);
        if ($pending) {
            return $pending;
        }
        $export = ReportExport::query()->create(['business_id' => $business->id, 'requested_by_membership_id' => $membership->id, 'report_key' => $reportKey, 'format' => 'csv', 'filters' => $normalized, 'scope_snapshot' => ['business_id' => $business->id, 'membership_id' => $membership->id, 'location_ids' => $scope['location_ids'], 'staff_ids' => $scope['staff_ids'], 'can_finance' => $scope['can_finance'], 'can_reasons' => $scope['can_reasons']], 'status' => 'queued']);
        GenerateReportExport::dispatch($export->id);

        return $export;
    }
}
