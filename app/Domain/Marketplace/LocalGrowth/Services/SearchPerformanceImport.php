<?php

namespace App\Domain\Marketplace\LocalGrowth\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SearchPerformanceImport
{
    public function import(array $rows, string $property, string $source, string $reviewer, bool $apply = false): int
    {
        Validator::make(compact('rows', 'property', 'source', 'reviewer'), [
            'property' => ['required', 'url:https', 'max:255'], 'source' => ['required', 'string', 'max:255'], 'reviewer' => ['required', 'string', 'max:255'],
            'rows' => ['present', 'array', 'max:10000'], 'rows.*' => ['array:day,path,impressions,clicks,position'],
            'rows.*.day' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'], 'rows.*.path' => ['required', 'string', 'max:255'],
            'rows.*.impressions' => ['required', 'integer', 'min:0'], 'rows.*.clicks' => ['required', 'integer', 'min:0'], 'rows.*.position' => ['nullable', 'numeric', 'min:1', 'max:10000'],
        ])->validate();
        if (rtrim($property, '/') !== rtrim((string) config('brand.website_url'), '/')) {
            throw ValidationException::withMessages(['property' => 'Use the approved primary property origin. No other site data can be imported.']);
        }
        $paths = DB::table('marketplace_seo_pages')->where('status', 'approved')->pluck('path')->all();
        $keys = [];
        foreach ($rows as $row) {
            if (! in_array($row['path'], $paths, true) || str_contains($row['path'], '?') || $row['clicks'] > $row['impressions'] || in_array($row['day'].'|'.$row['path'], $keys, true)) {
                throw ValidationException::withMessages(['rows' => 'Use unique day and approved canonical page rows with valid aggregate counts. Query/customer data is not accepted.']);
            }
            $keys[] = $row['day'].'|'.$row['path'];
        }
        if ($apply) {
            DB::transaction(function () use ($rows, $property, $source, $reviewer) {
                foreach ($rows as $row) {
                    $key = ['property' => rtrim($property, '/'), 'day' => $row['day'], 'path' => $row['path']];
                    $existing = DB::table('marketplace_search_performance')->where($key)->first();
                    DB::table('marketplace_search_performance')->updateOrInsert($key, ['impressions' => $row['impressions'], 'clicks' => $row['clicks'], 'position' => $row['position'] ?? null, 'source' => $source, 'reviewer' => $reviewer, 'created_at' => $existing?->created_at ?? now(), 'updated_at' => now()]);
                }
            });
        }

        return count($rows);
    }
}
