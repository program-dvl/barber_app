<?php

namespace App\Console\Commands;

use App\Domain\Marketplace\LocalGrowth\Services\SearchPerformanceImport;
use Illuminate\Console\Command;

class ImportMarketplaceSearchPerformance extends Command
{
    protected $signature = 'marketplace:growth-search-import {file : Aggregate JSON array only} {--property=} {--source=} {--reviewer=} {--apply}';

    protected $description = 'Validate/import authorized Search Console page/day totals; no credentials or customer queries';

    public function handle(SearchPerformanceImport $import): int
    {
        $rows = json_decode(file_get_contents($this->argument('file')), true, flags: JSON_THROW_ON_ERROR);
        $count = $import->import($rows, (string) $this->option('property'), (string) $this->option('source'), (string) $this->option('reviewer'), (bool) $this->option('apply'));
        $this->info(($this->option('apply') ? 'Imported ' : 'Validated ').$count.' aggregate rows. No Google request was sent.');

        return self::SUCCESS;
    }
}
