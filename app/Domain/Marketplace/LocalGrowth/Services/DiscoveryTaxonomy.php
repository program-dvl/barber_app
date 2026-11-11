<?php

namespace App\Domain\Marketplace\LocalGrowth\Services;

class DiscoveryTaxonomy
{
    public function concepts(string $name, ?string $type, array $reviewed = [], ?string $locale = null): array
    {
        $definitions = config('marketplace-taxonomy.concepts');
        if ($reviewed !== []) {
            return array_values(array_intersect($reviewed, array_keys($definitions)));
        }
        if ($locale && ! str_starts_with(strtolower($locale), 'en')) {
            return [];
        }
        $name = $this->normalize($name);
        $contexts = config('marketplace-taxonomy.operational_contexts.'.($type ?: 'other'), []);

        return array_keys(array_filter($definitions, function ($concept) use ($name, $contexts) {
            if (! in_array($name, array_map($this->normalize(...), $concept['aliases']), true)) {
                return false;
            }

            // Unambiguous explicit names are safe for mixed/other businesses.
            // Generic haircut/facial/treatment aliases require reviewed context.
            return ! in_array($name, ['signature cut', 'signature haircut', 'cut & finish', 'repair treatment', 'signature facial', 'radiance facial', 'relaxation ritual', 'colour refresh'], true)
                || count(array_intersect($contexts, $concept['families'])) > 0;
        }));
    }

    public function terms(array $concepts, ?string $type): array
    {
        $families = array_values(array_unique(array_merge([], ...array_map(fn ($key) => config('marketplace-taxonomy.concepts.'.$key.'.families', []), $concepts))));
        $audiences = array_keys(array_filter(config('marketplace-taxonomy.audiences'), fn ($fs) => count(array_intersect($families, $fs)) > 0));
        if ($type === 'independent_professional' && $families) {
            $audiences[] = 'independent-stylists';
        }

        return ['concept' => $concepts, 'family' => $families, 'audience' => $audiences];
    }

    public function normalize(string $value): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($value)));
    }

    public function category(string $key): string
    {
        return ['hair' => 'salons', 'barber' => 'barbershops', 'nails' => 'nail-salons'][$key] ?? $key;
    }
}
