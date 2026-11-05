<?php

namespace App\Support\Seo;

use App\Domain\HelpCenter\HelpCatalog;

class ProductEvidence
{
    public function screenshots(array $names): array
    {
        $screens = collect($names)->map(fn (string $name) => config('product-tour.screens.'.$name))->filter()->values()->all();

        // Reuse the reviewed public demo-media manifest and its integrity checks.
        return app(HelpCatalog::class)->screenshots(['screenshots' => $screens]);
    }
}
