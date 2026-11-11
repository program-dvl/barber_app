<?php

namespace App\Console\Commands;

use App\Domain\Marketplace\LocalGrowth\Services\SeoPageRegistry;
use App\Domain\PlatformAccess\Models\Business;
use App\Support\Audit\AuditWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewMarketplacePages extends Command
{
    protected $signature = 'marketplace:growth-pages {file : JSON array of individually reviewed pages} {--reviewer=} {--apply : Apply reviewed statuses only with matching hashes}';

    protected $description = 'Review real inventory-backed pages; no route permutations or global indexation changes';

    public function handle(SeoPageRegistry $registry): int
    {
        $rows = json_decode(file_get_contents($this->argument('file')), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($rows) || count($rows) > 100 || ! trim((string) $this->option('reviewer'))) {
            $this->error('Provide up to 100 explicit pages and an accountable reviewer.');

            return self::FAILURE;
        }
        DB::transaction(function () use ($rows, $registry) {
            foreach ($rows as $row) {
                $kind = $row['kind'] ?? '';
                $status = $row['status'] ?? 'draft';
                if (! in_array($kind, ['entry', 'country', 'city', 'audience', 'service', 'profile'], true) || ! in_array($status, ['draft', 'ready', 'approved', 'paused', 'withdrawn'], true)) {
                    throw ValidationException::withMessages(['page' => 'Choose a supported page and publication state.']);
                }
                $business = $kind === 'profile' ? Business::query()->where('public_id', $row['business'] ?? '')->firstOrFail() : null;
                $geo = in_array($kind, ['city', 'audience', 'service'], true) ? DB::table('marketplace_geographies')->where('public_id', $row['geography'] ?? '')->first() : null;
                if (in_array($kind, ['city', 'audience', 'service'], true) && ! $geo) {
                    throw ValidationException::withMessages(['geography' => 'Choose a reviewed stable geography.']);
                }
                $term = in_array($kind, ['audience', 'service'], true) ? $row['term'] ?? '' : null;
                if (($kind === 'audience' && ! array_key_exists($term, config('marketplace-taxonomy.audiences'))) || ($kind === 'service' && ! array_key_exists($term, config('marketplace-taxonomy.concepts')))) {
                    throw ValidationException::withMessages(['term' => 'Choose a reviewed audience or service concept.']);
                }
                $path = match ($kind) {
                    'entry' => '/marketplace', 'profile' => '/marketplace/salons/'.$business->booking_slug,
                    'country' => '/marketplace/'.($row['country'] ?? ''),
                    default => '/marketplace/'.$geo->country.'/'.$geo->region.'/'.$geo->city.($kind === 'audience' ? '/'.$term : ($kind === 'service' ? '/services/'.$term : '')),
                };
                if ($kind === 'country' && ! preg_match('/^[a-z]{2}$/D', $row['country'] ?? '')) {
                    throw ValidationException::withMessages(['country' => 'Choose a two-letter country code.']);
                }
                $old = DB::table('marketplace_seo_pages')->where('path', $path)->lockForUpdate()->first();
                $values = ['kind' => $kind, 'path' => $path, 'business_id' => $business?->id, 'geography_id' => $geo?->id, 'term' => $term, 'title' => trim($row['title'] ?? $old?->title ?? ''), 'context' => trim($row['context'] ?? $old?->context ?? '')];
                if (mb_strlen($values['title']) > 255 || mb_strlen($values['context']) > 10000 || preg_match('/<[^>]*>|https?:\/\//i', $values['title'].$values['context'])) {
                    throw ValidationException::withMessages(['content' => 'Use bounded, reviewed plain text without markup or external URLs.']);
                }
                $readiness = $registry->readiness((object) $values);
                $this->line(json_encode(['path' => $path, 'ready' => $readiness['ready'], 'businesses' => $readiness['businesses'], 'minimum' => $readiness['minimum'], 'hash' => $readiness['hash'], 'status' => $status]));
                if (! $this->option('apply')) {
                    continue;
                }
                if ($status === 'approved' && (! $readiness['ready'] || ! hash_equals($readiness['hash'], (string) ($row['hash'] ?? '')) || ($kind === 'profile' && ! $old?->requested_at))) {
                    throw ValidationException::withMessages(['approval' => 'Review current content and inventory, retain its exact hash, and obtain the owner’s publication request before approval.']);
                }
                $values += ['status' => $status, 'approved_hash' => $status === 'approved' ? $readiness['hash'] : null, 'inventory_hash' => $readiness['hash'], 'reviewer' => $this->option('reviewer'), 'reviewed_at' => now(), 'materially_changed_at' => $old && $old->inventory_hash === $readiness['hash'] ? $old->materially_changed_at : now(), 'updated_at' => now()];
                DB::table('marketplace_seo_pages')->updateOrInsert(['path' => $path], [...$values, 'created_at' => $old?->created_at ?? now()]);
                app(AuditWriter::class)->write('marketplace.seo.reviewed', $business, reason: 'Reviewed page publication', before: ['status' => $old?->status], after: ['status' => $status], metadata: ['path' => $path, 'reviewer' => $this->option('reviewer'), 'content_hash' => $readiness['hash']], source: 'operator_command');
            }
        });

        return self::SUCCESS;
    }
}
