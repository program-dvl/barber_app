<?php

namespace App\Domain\Reporting\Jobs;

use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Membership;
use App\Domain\Reporting\Models\ReportExport;
use App\Domain\Reporting\Services\InstrumentationService;
use App\Domain\Reporting\Services\ReportCatalog;
use App\Domain\Reporting\Services\ReportMoney;
use App\Domain\Reporting\Services\ReportService;
use App\Support\Files\TenantPrivateStorage;
use App\Support\Jobs\DispatchesInTenant;
use App\Support\Jobs\TenantAwareJob;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

class GenerateReportExport implements ShouldQueue, TenantAwareJob
{
    use Dispatchable, DispatchesInTenant, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public int $reportExportId)
    {
        $export = ReportExport::query()->findOrFail($reportExportId);
        $this->initializeTenantPayload($export->business_id);
        $this->onQueue('exports');
    }

    public function handle(ReportService $reports, TenantPrivateStorage $storage, InstrumentationService $instrumentation): void
    {
        $export = ReportExport::query()->forBusiness($this->businessId)->findOrFail($this->reportExportId);
        if ($export->status === 'completed') {
            return;
        }
        $export->update(['status' => 'processing', 'error' => null]);
        try {
            $business = Business::query()->findOrFail($this->businessId);
            $membership = Membership::query()->forBusiness($business)->active()->findOrFail($export->requested_by_membership_id);
            if (! $membership->hasPermissionTo(PermissionName::ExportCreate->value, 'web')) {
                throw new AccessDeniedHttpException('Export permission is required.');
            }
            [$result, $csv] = DB::transaction(function () use ($reports, $business, $membership, $export) {
                $result = $reports->run($business, $membership, $export->report_key, $export->filters);

                return [$result, $this->toCsv($result, $reports->records($business, $membership, $export->report_key, $export->filters))];
            });
            $path = "files/exports/{$export->public_id}.csv";
            $storagePath = $storage->putStream($business, $path, $csv);
            $export->update(['status' => 'completed', 'storage_path' => $storagePath, 'content_hash' => $this->streamHash($csv), 'row_count' => $result['totals']['row_count'], 'totals' => $result['totals'], 'completed_at' => now()]);
            fclose($csv);
            $instrumentation->record($business, 'report.export_completed', "report-export:{$export->id}", ['source' => $export->report_key]);
        } catch (Throwable $exception) {
            $export->update(['status' => 'failed', 'error' => 'Report generation failed. Retry or contact support.']);
            throw $exception;
        }
    }

    /** CSV keeps audit totals in original minor units; readable rows use major units. */
    private function toCsv(array $result, iterable $rows)
    {
        $stream = fopen('php://temp/maxmemory:2097152', 'w+');
        foreach (['metric_version', 'fresh_at', 'time_zone', 'currency_code', 'source'] as $key) {
            fputcsv($stream, [$key, $result[$key]], escape: '');
        }
        fputcsv($stream, ['Definitions', json_encode(ReportCatalog::definitions())], escape: '');
        fputcsv($stream, ['Date range', $result['filters']['start_date'], $result['filters']['end_date']], escape: '');
        fputcsv($stream, ['Filters', json_encode($result['filters'])], escape: '');
        fputcsv($stream, [], escape: '');
        $columns = array_values(array_filter($result['columns'], fn ($column) => ! in_array($column, ['drill', 'source_id', 'entry_kind']) && ! str_ends_with($column, '_id')));
        fputcsv($stream, array_map(fn ($column) => ($column === 'net_minor' ? (in_array($result['report_key'], ['sales', 'overview', 'tax', 'location'], true) ? 'Sales less returns' : 'Line sales after discounts (before returns)') : ucfirst(str_replace('_', ' ', preg_replace('/_(minor|percent)$/', '', $column)))).(str_ends_with($column, '_minor') ? ' ('.$result['currency_code'].')' : (str_ends_with($column, '_percent') ? ' (%)' : '')), $columns), escape: '');
        foreach ($rows as $row) {
            fputcsv($stream, array_map(function ($column) use ($row, $result) {
                $value = $row[$column] ?? '';
                if ($value === null) {
                    return '';
                }
                if (str_ends_with($column, '_minor')) {
                    return ReportMoney::decimal($value, $result['currency_code']);
                }
                if (str_ends_with($column, '_at') && $value !== '') {
                    $value = CarbonImmutable::parse($value, 'UTC')->setTimezone($result['time_zone'])->format('Y-m-d H:i:s P');
                }
                // Text, including formula-like client/service labels, is inert.
                if (is_string($value) && preg_match('/^[\s]*[=+@\-\t\r]/u', $value)) {
                    return "'".$value;
                }

                return is_scalar($value) ? $value : '';
            }, $columns), escape: '');
        }
        fputcsv($stream, [], escape: '');
        foreach ($result['totals'] as $key => $value) {
            fputcsv($stream, ["total:{$key}", $value], escape: '');
        }
        rewind($stream);

        return $stream;
    }

    private function streamHash($stream): string
    {
        rewind($stream);
        $hash = hash_init('sha256');
        hash_update_stream($hash, $stream);
        rewind($stream);

        return hash_final($hash);
    }
}
