<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ReviewMarketplaceGeography extends Command
{
    protected $signature = 'marketplace:growth-geographies {file : Reviewed JSON array} {--reviewer= : Accountable reviewer} {--apply : Save reviewed geography; never enrolls businesses}';

    protected $description = 'Validate/import provenance-bearing country/region/city identities';

    public function handle(): int
    {
        $rows = json_decode(file_get_contents($this->argument('file')), true, flags: JSON_THROW_ON_ERROR);
        Validator::make(['rows' => $rows, 'reviewer' => $this->option('reviewer')], [
            'reviewer' => ['required', 'string', 'max:255'], 'rows' => ['required', 'array', 'max:200'],
            'rows.*.country' => ['required', 'regex:/^[a-z]{2}$/D'], 'rows.*.region' => ['required', 'regex:/^[a-z0-9-]{1,60}$/D'],
            'rows.*.city' => ['required', 'regex:/^[a-z0-9-]{1,100}$/D'], 'rows.*.name' => ['required', 'string', 'max:255'], 'rows.*.source' => ['required', 'string', 'max:255'],
        ])->validate();
        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                $key = collect($row)->only(['country', 'region', 'city'])->all();
                $old = DB::table('marketplace_geographies')->where($key)->first();
                if ($this->option('apply')) {
                    DB::table('marketplace_geographies')->updateOrInsert($key, ['public_id' => $old?->public_id ?? (string) Str::ulid(), 'name' => $row['name'], 'source' => $row['source'], 'reviewer' => $this->option('reviewer'), 'reviewed_at' => now(), 'created_at' => $old?->created_at ?? now(), 'updated_at' => now()]);
                }
                $this->line(json_encode(['path' => implode('/', $key), 'public_id' => $old?->public_id, 'saved' => (bool) $this->option('apply')]));
            }
        });

        return self::SUCCESS;
    }
}
