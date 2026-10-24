<?php

namespace App\Domain\HelpCenter;

use Illuminate\Support\Str;

/** Bounded local search for a small reviewed corpus; no external service or tenant data. */
class HelpSearch
{
    private const STOP_WORDS = ['a', 'an', 'and', 'are', 'as', 'at', 'be', 'can', 'could', 'do', 'does', 'for', 'from', 'how', 'i', 'in', 'is', 'it', 'me', 'my', 'of', 'on', 'please', 'the', 'their', 'this', 'to', 'using', 'what', 'when', 'where', 'which', 'who', 'why', 'with', 'you', 'your'];

    public function __construct(private readonly HelpCatalog $catalog) {}

    public function query(string $query, int $limit = 30): array
    {
        $query = mb_substr(trim($query), 0, 180);
        $terms = array_slice($this->tokens($query), 0, 14);
        if ($terms === []) {
            return [];
        }
        $categoryTitles = collect(config('help.categories'))->pluck('title', 'slug')->all();
        $documents = [];
        foreach ($this->catalog->articles() as $article) {
            $title = $this->tokens($article['title']);
            $keywords = $this->tokens(implode(' ', $article['keywords']));
            $summary = $this->tokens($article['summary']);
            $body = $this->tokens(mb_substr(implode(' ', array_column($article['sections'], 'body')), 0, 35000));
            $documents[] = compact('article', 'title', 'keywords', 'summary', 'body');
        }
        $aliasesByTerm = [];
        $weights = [];
        foreach ($terms as $term) {
            $aliasesByTerm[$term] = $this->aliases($term);
            $frequency = count(array_filter($documents, fn ($document) => in_array($term, [...$document['title'], ...$document['keywords'], ...$document['summary'], ...$document['body']], true)));
            $weights[$term] = 1 + log((count($documents) + 1) / ($frequency + 1));
        }
        $scores = [];
        foreach ($documents as $document) {
            ['article' => $article, 'title' => $title, 'keywords' => $keywords, 'summary' => $summary, 'body' => $body] = $document;
            $score = 0;
            $matched = 0;
            foreach ($terms as $term) {
                $aliases = $aliasesByTerm[$term];
                $termScore = max(
                    $this->match($term, $aliases, $title) * 12,
                    $this->match($term, $aliases, $keywords) * 9,
                    $this->match($term, $aliases, $summary) * 6,
                    $this->match($term, $aliases, $body) * 1.5,
                );
                if ($termScore > 0) {
                    $matched++;
                    $categoryMatch = $this->match($term, $aliases, $this->tokens($categoryTitles[$article['category']] ?? ''));
                    $score += ($termScore + 8 * $categoryMatch) * $weights[$term];
                }
            }
            if ($matched === 0 || $matched / count($terms) < 0.5) {
                continue;
            }
            $normalized = $this->normalize($query);
            if (count($terms) >= 2 && str_contains($this->normalize($article['title']), $normalized)) {
                $score += 45;
            }
            $score *= $matched / count($terms);
            if ($score >= 5) {
                $scores[] = ['score' => $score, 'article' => $article];
            }
        }
        usort($scores, fn ($a, $b) => ($b['score'] <=> $a['score']) ?: (($a['article']['order'] ?? 100) <=> ($b['article']['order'] ?? 100)) ?: strcmp($a['article']['title'], $b['article']['title']));

        return array_map(fn ($row) => $this->catalog->summary($row['article']), array_slice($scores, 0, max(1, min($limit, 50))));
    }

    private function normalize(string $value): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower(Str::ascii($value))));
    }

    private function tokens(string $value): array
    {
        $words = array_filter(explode(' ', $this->normalize($value)), fn ($word) => strlen($word) > 1 && ! in_array($word, self::STOP_WORDS, true));

        return array_values(array_unique(array_map(fn ($word) => strlen($word) > 3 && str_ends_with($word, 's') && ! str_ends_with($word, 'ss') ? substr($word, 0, -1) : $word, $words)));
    }

    private function aliases(string $term): array
    {
        $aliases = [$term];
        foreach (config('help.synonyms', []) as $group) {
            $groupWords = $this->tokens(implode(' ', $group));
            if (in_array($term, $groupWords, true)) {
                $aliases = [...$aliases, ...$groupWords];
            }
        }

        return array_values(array_unique($aliases));
    }

    private function match(string $term, array $aliases, array $haystack): float
    {
        if (in_array($term, $haystack, true)) {
            return 1.0;
        }
        if (array_intersect($aliases, $haystack) !== []) {
            return 0.65;
        }
        $best = 0;
        foreach ($haystack as $word) {
            if (strlen($term) >= 3 && str_starts_with($word, $term)) {
                $best = max($best, 0.75);
            } elseif (strlen($term) >= 4 && abs(strlen($term) - strlen($word)) <= 2
                && levenshtein($term, $word) <= (strlen($term) >= 7 ? 2 : 1)) {
                $best = max($best, 0.5);
            }
        }

        return $best;
    }
}
