<?php

namespace App\Domain\Marketplace\LocalGrowth\Services;

use App\Support\Seo\PublicUrl;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GrowthSitemap
{
    public function pages(): Collection
    {
        if (! config('marketplace-growth.enabled') || ! config('marketplace-growth.indexation_enabled')) {
            return collect();
        }

        return DB::table('marketplace_seo_pages')->where('status', 'approved')->orderBy('id')->get()
            ->filter(fn ($page) => app(SeoPageRegistry::class)->indexable($page))->values();
    }

    public function index(): string
    {
        $shards = $this->pages()->map(fn ($p) => [$p->kind === 'profile' ? 'profiles' : 'hubs', intdiv($p->id - 1, 500)])->unique(fn ($s) => implode('/', $s));
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($shards as [$kind, $shard]) {
            $xml .= '<sitemap><loc>'.$this->escape(PublicUrl::canonical(url('/marketplace/sitemaps/'.$kind.'/'.$shard.'.xml'))).'</loc></sitemap>';
        }

        return $xml.'</sitemapindex>';
    }

    public function shard(string $kind, int $shard): string
    {
        $pages = $this->pages()->filter(fn ($p) => ($p->kind === 'profile' ? 'profiles' : 'hubs') === $kind && intdiv($p->id - 1, 500) === $shard);
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($pages as $page) {
            $xml .= '<url><loc>'.$this->escape(PublicUrl::canonical(url($page->path))).'</loc>';
            if ($page->materially_changed_at) {
                $xml .= '<lastmod>'.CarbonImmutable::parse($page->materially_changed_at)->utc()->toIso8601String().'</lastmod>';
            }
            $xml .= '</url>';
        }

        return $xml.'</urlset>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
