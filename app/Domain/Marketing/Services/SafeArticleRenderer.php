<?php

namespace App\Domain\Marketing\Services;

use Illuminate\Support\Str;

class SafeArticleRenderer
{
    public function render(string $markdown): string
    {
        $html = Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
        ]);
        // The page title owns h1; article authors may use headings in Markdown.
        if (str_contains($html, '<h1>')) {
            $html = preg_replace_callback('/<(\/?)(h)([1-6])>/',
                fn (array $match) => '<'.$match[1].'h'.min(6, (int) $match[3] + 1).'>', $html);
        }

        return $html;
    }
}
