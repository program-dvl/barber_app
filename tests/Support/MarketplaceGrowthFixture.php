<?php

use App\Domain\Marketplace\LocalGrowth\Services\PublicSupplyProjection;
use App\Domain\Marketplace\LocalGrowth\Services\SeoPageRegistry;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/MarketplaceFixture.php';

function growthSupply(string $serviceName = 'Dog grooming', string $type = 'other'): array
{
    config(['marketplace-growth.enabled' => false]);
    $f = marketplaceBookablePath();
    $f['business']->update(['business_type' => $type, 'description' => 'Appointment services provided at our customer-facing studio. Choose a published service and review your appointment before confirming.', 'country_code' => 'CA', 'currency_code' => 'CAD', 'locale' => 'en-CA']);
    $f['location']->update(['address' => '10 Example Street, Test City, Ontario, Canada', 'country_code' => 'CA', 'time_zone' => 'America/Toronto']);
    $f['service']->update(['name' => $serviceName, 'currency_code' => 'CAD']);
    $geo = DB::table('marketplace_geographies')->where('country', 'ca')->first();
    if (! $geo) {
        $id = DB::table('marketplace_geographies')->insertGetId(['public_id' => (string) Str::ulid(), 'country' => 'ca', 'region' => 'on', 'city' => 'test-city', 'name' => 'Test City', 'source' => 'isolated synthetic fixture; never public inventory', 'reviewer' => 'Test reviewer', 'reviewed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $geo = DB::table('marketplace_geographies')->find($id);
    }
    DB::table('marketplace_location_geographies')->insert(['business_id' => $f['business']->id, 'location_id' => $f['location']->id, 'geography_id' => $geo->id, 'address_hash' => hash('sha256', $f['location']->address), 'reviewed_by' => $f['user']->id, 'address_public_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    config(['marketplace-growth.enabled' => true]);
    app(PublicSupplyProjection::class)->rebuild($f['business']->id);

    return [...$f, 'geography' => $geo];
}

function growthApproveProfile(array $f): object
{
    $registry = app(SeoPageRegistry::class);
    $ready = $registry->requestProfile($f['business']);
    DB::table('marketplace_seo_pages')->where('business_id', $f['business']->id)->update(['status' => 'approved', 'approved_hash' => $ready['hash'], 'reviewer' => 'Test reviewer', 'reviewed_at' => now()]);

    return DB::table('marketplace_seo_pages')->where('business_id', $f['business']->id)->first();
}

function growthApproveHub(array $f, string $kind = 'service', string $term = 'dog-grooming'): object
{
    $values = ['kind' => $kind, 'path' => '/marketplace/ca/on/test-city'.($kind === 'service' ? '/services/'.$term : ($kind === 'audience' ? '/'.$term : '')), 'business_id' => null, 'geography_id' => $f['geography']->id, 'term' => $term, 'title' => 'Dog grooming appointments in Test City', 'context' => 'Compare the participating businesses offering dog grooming appointments at their published customer-facing addresses in Test City. Review each provider’s services and starting prices before choosing an appointment.'];
    $ready = app(SeoPageRegistry::class)->readiness((object) $values);
    $id = DB::table('marketplace_seo_pages')->insertGetId([...$values, 'status' => 'approved', 'approved_hash' => $ready['hash'], 'inventory_hash' => $ready['hash'], 'reviewer' => 'Test reviewer', 'reviewed_at' => now(), 'materially_changed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

    return DB::table('marketplace_seo_pages')->find($id);
}

function growthMainHtml($response): string
{
    $doc = new DOMDocument;
    @$doc->loadHTML($response->getContent());

    return implode(' ', array_map(fn ($node) => $node->textContent, iterator_to_array($doc->getElementsByTagName('main'))));
}
