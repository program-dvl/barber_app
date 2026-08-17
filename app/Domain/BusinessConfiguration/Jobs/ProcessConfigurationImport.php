<?php

namespace App\Domain\BusinessConfiguration\Jobs;

use App\Domain\BusinessConfiguration\Models\ConfigurationImport;
use App\Domain\BusinessConfiguration\Services\ConfigurationImportService;
use App\Support\Audit\AuditWriter;
use App\Support\Jobs\DispatchesInTenant;
use App\Support\Jobs\TenantAwareJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessConfigurationImport implements ShouldQueue, TenantAwareJob
{
    use Dispatchable;
    use DispatchesInTenant;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    /** @param array<int, string> $duplicateResolutions */
    public function __construct(public int $importId, public array $duplicateResolutions = [])
    {
        $import = ConfigurationImport::query()->findOrFail($importId);
        $this->initializeTenantPayload($import->business_id);
        $this->onQueue('imports');
    }

    public function handle(ConfigurationImportService $imports, AuditWriter $audit): void
    {
        $import = ConfigurationImport::query()->forBusiness($this->businessId)->findOrFail($this->importId);
        $result = $imports->commit($import, $this->duplicateResolutions);
        $audit->write('configuration.import.completed', $result->business, target: $result, after: $result->only([
            'entity_type', 'created_rows', 'updated_rows', 'skipped_rows', 'failed_rows', 'duplicate_rows',
        ]), source: 'job', correlationId: $this->correlationId());
    }
}
