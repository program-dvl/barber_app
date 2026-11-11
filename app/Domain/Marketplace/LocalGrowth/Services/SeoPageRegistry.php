<?php

namespace App\Domain\Marketplace\LocalGrowth\Services;

use App\Domain\PlatformAccess\Models\Business;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SeoPageRegistry
{
    public function offers(object $page): Builder
    {
        $public = app(PublicOfferQuery::class);
        $q = $public->query();
        if ($page->kind !== 'profile') {
            $q->whereNotNull('o.geography_id');
        }
        if ($page->business_id) {
            $q->where('o.business_id', $page->business_id);
        }
        if ($page->geography_id) {
            $public->geography($q, $page->geography_id);
        } elseif ($page->kind === 'country') {
            $q->whereIn('o.geography_id', DB::table('marketplace_geographies')->where('country', explode('/', $page->path)[2])->select('id'));
        }
        if (in_array($page->kind, ['audience', 'service'], true)) {
            $public->term($q, $page->kind === 'service' ? 'concept' : 'audience', $page->term);
        }

        return $q;
    }

    public function readiness(object $page): array
    {
        $offers = $this->offers($page)->orderBy('o.id')->get(['o.*']);
        $count = $offers->unique('business_id')->count();
        $facts = $offers->map(fn ($o) => [$o->id, $o->content_hash])->all();
        $hash = hash('sha256', json_encode([$page->path, $page->title, $page->context, $facts], JSON_THROW_ON_ERROR));
        $minimum = $page->kind === 'profile' ? 1 : (int) config('marketplace-growth.hub_minimum_businesses');
        $ready = $count >= $minimum && trim($page->title) !== '' && ($page->kind === 'profile' || mb_strlen(trim((string) $page->context)) >= 80);
        if ($page->kind === 'profile') {
            $ready = $ready && $offers->contains(fn ($o) => trim((string) $o->business_name) !== '' && trim((string) $o->service_name) !== '' && trim((string) $o->address) !== '');
        }

        return ['ready' => $ready, 'businesses' => $count, 'minimum' => $minimum, 'hash' => $hash, 'offers' => $offers];
    }

    public function approved(object $page): bool
    {
        if ($page->status !== 'approved' || ! $page->reviewed_at || ! $page->reviewer) {
            return false;
        }
        $ready = $this->readiness($page);
        // Hubs tolerate supply changes while retaining editorial approval; current
        // usefulness still gates indexing. Profile material facts require re-review.
        if ($page->kind !== 'profile' && ! $ready['ready'] && $ready['businesses'] > 0 && ($page->low_supply_since ?? null) && mb_strlen(trim((string) $page->context)) >= 80) {
            return CarbonImmutable::parse($page->low_supply_since)->addDays(config('marketplace-growth.hub_review_days'))->gt(now());
        }

        return $ready['ready'] && ($page->kind !== 'profile' || hash_equals((string) $page->approved_hash, $ready['hash']));
    }

    public function indexable(object $page): bool
    {
        if (! config('marketplace-growth.enabled') || ! config('marketplace-growth.indexation_enabled') || ! $this->approved($page)) {
            return false;
        }
        $origin = rtrim((string) config('brand.website_url'), '/');
        $parts = parse_url($origin);

        return $parts && ($parts['scheme'] ?? '') === 'https' && ! empty($parts['host'])
            && ! isset($parts['query']) && ! isset($parts['fragment'])
            && ! isset($parts['user']) && ! isset($parts['pass'])
            && in_array($parts['path'] ?? '', ['', '/'], true)
            && request()->getSchemeAndHttpHost() === $origin;
    }

    public function syncProfile(Business $business): void
    {
        $page = DB::table('marketplace_seo_pages')->where('business_id', $business->id)->where('kind', 'profile')->first();
        if ($page && in_array($page->status, ['paused', 'withdrawn'], true)) {
            return;
        }
        if (! filled($business->booking_slug)) {
            if ($page) {
                DB::table('marketplace_seo_pages')->where('id', $page->id)->update(['status' => 'draft', 'approved_hash' => null, 'updated_at' => now()]);
            }

            return;
        }
        $values = ['path' => '/marketplace/salons/'.$business->booking_slug, 'kind' => 'profile', 'business_id' => $business->id,
            'geography_id' => null, 'term' => null, 'title' => mb_substr($business->name.' — services & appointments', 0, 255), 'context' => null];
        $readiness = $this->readiness((object) $values);
        if (! $page && $readiness['businesses'] === 0) {
            return;
        }
        $values += ['status' => $readiness['ready'] ? 'approved' : 'draft',
            'approved_hash' => $readiness['ready'] ? $readiness['hash'] : null,
            'inventory_hash' => $readiness['hash'], 'reviewer' => 'automatic-profile-policy-v1',
            'reviewed_at' => now(), 'requested_at' => $page?->requested_at ?? $business->configuration_published_at,
            'materially_changed_at' => $page && $page->inventory_hash === $readiness['hash'] ? $page->materially_changed_at : now(), 'updated_at' => now()];
        if ($page) {
            DB::table('marketplace_seo_pages')->where('id', $page->id)->update($values);
        } else {
            DB::table('marketplace_seo_pages')->insert([...$values, 'created_at' => now()]);
        }
    }

    public function requestProfile(Business $business): array
    {
        $path = '/marketplace/salons/'.$business->booking_slug;
        $values = ['path' => $path, 'kind' => 'profile', 'business_id' => $business->id, 'geography_id' => null, 'term' => null, 'title' => mb_substr($business->name.' — services & appointments', 0, 255), 'context' => null];
        $readiness = $this->readiness((object) $values);
        if (! $readiness['ready']) {
            throw ValidationException::withMessages(['publication' => 'Add your business name, a public address and a service with an online team member to complete your profile.']);
        }
        $this->syncProfile($business);

        return $readiness;
    }

    public function refreshMaterialChanges(int $businessId, array $geographies): void
    {
        $pages = DB::table('marketplace_seo_pages')->where(fn ($q) => $q->where('business_id', $businessId)->orWhereIn('geography_id', $geographies)->orWhereIn('kind', ['country', 'entry']))->get();
        foreach ($pages as $page) {
            $ready = $this->readiness($page);
            $lowSince = $page->kind !== 'profile' && $ready['businesses'] < $ready['minimum'] ? ($page->low_supply_since ?? now()) : null;
            if ($page->inventory_hash !== $ready['hash'] || ($page->low_supply_since ?? null) !== $lowSince) {
                DB::table('marketplace_seo_pages')->where('id', $page->id)->update(['inventory_hash' => $ready['hash'], 'low_supply_since' => $lowSince, 'materially_changed_at' => $page->inventory_hash === $ready['hash'] ? $page->materially_changed_at : now(), 'updated_at' => now()]);
            }
        }
    }

    public function publicLinks(?int $geography = null): array
    {
        return DB::table('marketplace_seo_pages')->where('status', 'approved')->where('kind', '!=', 'profile')
            ->when($geography, fn ($q) => $q->where(fn ($q) => $q->where('geography_id', $geography)->orWhereIn('kind', ['country', 'entry'])))
            ->orderBy('path')->limit(100)->get()->filter(fn ($p) => $this->approved($p))->map(fn ($p) => ['name' => $p->title, 'url' => $p->path])->values()->all();
    }
}
