<?php

namespace App\Domain\Marketing\Services;

use Illuminate\Database\Eloquent\Builder;

class PublicContentPolicy
{
    // Shared by the publication query and the rendered-content regression crawl.
    public const BLOCKED_MARKERS = [
        'fr-', 'nfr-', 'adr-', 'open-', 'lorem ipsum', 'todo', 'tbd',
        'placeholder text', 'dummy content', 'acceptance criteria', 'qa notes',
        'implementation notes', 'development notes', 'larafast',
    ];

    public function containsInternalCopy(string $text): bool
    {
        $text = mb_strtolower(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5));
        foreach (self::BLOCKED_MARKERS as $marker) {
            if (str_contains($text, $marker)) {
                return true;
            }
        }

        return false;
    }

    public function constrainArticles(Builder $query): Builder
    {
        foreach (['title', 'content', 'excerpt', 'topic', 'seo_title', 'seo_description'] as $field) {
            $query->whereNotNull($field)->whereRaw('TRIM('.$field.") <> ''");
            foreach (self::BLOCKED_MARKERS as $marker) {
                $query->whereRaw('LOWER('.$field.') NOT LIKE ?', ['%'.$marker.'%']);
            }
        }

        return $query;
    }
}
