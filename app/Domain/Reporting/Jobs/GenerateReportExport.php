<?php

namespace App\Domain\Reporting\Jobs;

use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Membership;
use App\Domain\Reporting\Models\ReportExport;
use App\Domain\Reporting\Services\InstrumentationService;
use App\Domain\Reporting\Services\ReportService;
use App\Support\Files\TenantPrivateStorage;
use App\Support\Jobs\DispatchesInTenant;
use App\Support\Jobs\TenantAwareJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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
            $result = $reports->run($business, $membership, $export->report_key, $export->filters);
            $csv = $this->toCsv($result);
            $path = "files/exports/{$export->public_id}.csv";
            $storagePath = $storage->put($business, $path, $csv);
            $export->update(['status' => 'completed', 'storage_path' => $storagePath, 'content_hash' => hash('sha256', $csv), 'row_count' => count($result['rows']), 'totals' => $result['totals'], 'completed_at' => now()]);
            $instrumentation->record($business, 'report.export_completed', "report-export:{$export->id}", ['source' => $export->report_key]);
        } catch (Throwable $exception) {
            $export->update(['status' => 'failed', 'error' => str($exception->getMessage())->limit(500)->toString()]);
            throw $exception;
        }
    }

    /** @param array<string,mixed> $result */
    private function toCsv(array $result): string
    {
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, ['metric_version', $result['metric_version']]);
        fputcsv($stream, ['fresh_at', $result['fresh_at']]);
        fputcsv($stream, ['time_zone', $result['time_zone']]);
        fputcsv($stream, []);
        fputcsv($stream, $result['columns']);
        foreach ($result['rows'] as $row) {
            fputcsv($stream, array_map(fn ($column) => is_scalar($row[$column] ?? null) ? $row[$column] : json_encode($row[$column]), $result['columns']));
        }
        fputcsv($stream, []);
        foreach ($result['totals'] as $key => $value) {
            fputcsv($stream, ["total:{$key}", $value]);
        }
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        return $contents;
    }
}
