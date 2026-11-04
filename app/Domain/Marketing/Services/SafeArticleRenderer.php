<?php

namespace App\Domain\Marketing\Services;

use Illuminate\Support\Str;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class SafeArticleRenderer
{
    private readonly HtmlSanitizer $sanitizer;

    public function __construct()
    {
        $this->sanitizer = new HtmlSanitizer((new HtmlSanitizerConfig)
            ->allowSafeElements()
            ->allowRelativeLinks()
            ->allowRelativeMedias()
            ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
            ->allowMediaSchemes(['http', 'https'])
            ->withMaxInputLength(500000));
    }

    public function render(string $content): string
    {
        // RichEditor stores HTML; older articles store Markdown. Parse both,
        // then apply the same HTML security boundary before public rendering.
        $html = $this->sanitizer->sanitize(Str::markdown($content, [
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
            // Let the sanitizer remove unsafe HTML instead of showing it as text.
            'disallowed_raw_html' => ['disallowed_tags' => []],
        ]));
        // The page title owns h1; article authors may use headings in Markdown.
        if (preg_match('/<h1(?:\s|>)/', $html)) {
            $html = preg_replace_callback('/<(\/?)h([1-6])(?=[\s>])/',
                fn (array $match) => '<'.$match[1].'h'.min(6, (int) $match[2] + 1), $html);
        }

        return $html;
    }
}
