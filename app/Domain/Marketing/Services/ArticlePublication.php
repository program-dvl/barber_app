<?php

namespace App\Domain\Marketing\Services;

use App\Domain\PlatformAccess\Enums\PlatformRole;
use App\Models\Article;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ArticlePublication
{
    private const REVIEWED_FIELDS = ['title', 'content', 'excerpt', 'topic', 'thumbnail', 'seo_title', 'seo_description', 'seo_keywords'];

    public function prepare(array $data, User $actor, ?Article $article = null): array
    {
        abort_unless($actor->hasVerifiedEmail() && $actor->hasPlatformRole(PlatformRole::Administrator), 403);

        $publishing = ($data['status'] ?? 'draft') === 'published';
        $now = now('UTC');
        $changed = $article && collect(self::REVIEWED_FIELDS)->contains(
            fn ($field) => array_key_exists($field, $data)
                && ($data[$field] ?? '') !== ($article->getAttribute($field) ?? '')
        );

        // Review and publication history belongs to the saved record and actor,
        // never a client-supplied reviewer or date hidden in form state.
        foreach (['content_owner', 'reviewed_at', 'published_at', 'materially_updated_at'] as $field) {
            $data[$field] = $article?->getAttribute($field);
        }
        if ($changed) {
            $data['materially_updated_at'] = $now;
        }
        if ($publishing) {
            $reviewer = trim((string) $actor->name);
            if ($reviewer === '' || mb_strlen($reviewer) > 255) {
                throw ValidationException::withMessages(['data.status' => 'Add your name to your account before publishing.']);
            }

            // Publishing means now. Preserve an actual first publication date,
            // but an old future schedule must not silently hide this explicit save.
            $publishingNow = ! $article?->published_at || $article->published_at->greaterThan($now);
            if ($publishingNow) {
                $data['published_at'] = $now;
            }
            if (! $article || $article->status !== 'published' || $publishingNow || $changed
                || blank($article->content_owner) || ! $article->reviewed_at || $article->reviewed_at->greaterThan($now)) {
                $data['content_owner'] = $reviewer;
                $data['reviewed_at'] = $now;
            }
        }

        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:100000'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'excerpt' => [Rule::requiredIf($publishing), 'nullable', 'string', 'max:320'],
            'topic' => [Rule::requiredIf($publishing), 'nullable', 'string', 'max:64'],
            'thumbnail' => [Rule::requiredIf($publishing), 'nullable', 'string'],
            'seo_title' => [Rule::requiredIf($publishing), 'nullable', 'string', 'max:60'],
            'seo_description' => [Rule::requiredIf($publishing), 'nullable', 'string', 'max:160'],
        ];
        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            throw ValidationException::withMessages(collect($validator->errors()->messages())
                ->mapWithKeys(fn ($messages, $field) => ['data.'.$field => $messages])->all());
        }

        if ($publishing) {
            $errors = [];
            foreach (['title', 'content', 'excerpt', 'topic', 'seo_title', 'seo_description'] as $field) {
                // Match the stored SQL publication filter as well as decoded
                // visible text, so "Published" never hides a blocked attribute.
                $value = $data[$field] ?? '';
                if (app(PublicContentPolicy::class)->containsInternalCopy($value)
                    || Str::contains(mb_strtolower($value), PublicContentPolicy::BLOCKED_MARKERS)) {
                    $errors['data.'.$field] = 'Remove unfinished notes or internal reference codes, including in links, before publishing.';
                }
            }

            // The HTML sanitizer has a 500 kB safety bound. Check Markdown's
            // expansion first rather than silently publishing a truncated body.
            $expanded = Str::markdown($data['content'], [
                'html_input' => 'allow', 'allow_unsafe_links' => false,
                'max_nesting_level' => 20, 'disallowed_raw_html' => ['disallowed_tags' => []],
            ]);
            if (strlen($expanded) > 500000) {
                $errors['data.content'] = 'This article is too long. Split it into shorter articles before publishing.';
            }

            $html = app(SafeArticleRenderer::class)->render($data['content']);
            if (preg_match('/[^\p{Z}\s]/u', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5)) !== 1) {
                $errors['data.content'] = 'Add article text before publishing.';
            }
            if ($this->containsPrivateUrl($html)) {
                $errors['data.content'] = 'Remove private appointment, client, team or temporary image links before publishing. Use public article links and uploaded images.';
            }
            if ($errors) {
                throw ValidationException::withMessages($errors);
            }
        }

        // The retained schema stores an empty image path for an unfinished draft.
        $data['thumbnail'] = $data['thumbnail'] ?? '';

        return $data;
    }

    private function containsPrivateUrl(string $html): bool
    {
        $document = new DOMDocument;
        @$document->loadHTML('<meta charset="utf-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $nodes = (new DOMXPath($document))->query('//*[@href or @src or @srcset or @poster or @cite]');
        foreach ($nodes as $node) {
            foreach (['href', 'src', 'poster', 'cite'] as $attribute) {
                if ($node->hasAttribute($attribute) && $this->isPrivateUrl($node->getAttribute($attribute))) {
                    return true;
                }
            }
            foreach (explode(',', $node->getAttribute('srcset')) as $candidate) {
                $url = preg_split('/\s+/', trim($candidate))[0] ?? '';
                if ($url !== '' && $this->isPrivateUrl($url)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isPrivateUrl(string $url): bool
    {
        $parts = parse_url(html_entity_decode($url, ENT_QUOTES | ENT_HTML5));
        if ($parts === false) {
            return false;
        }
        parse_str($parts['query'] ?? '', $query);
        foreach (array_keys($query) as $key) {
            if (in_array(strtolower($key), ['signature', 'x-amz-signature', 'x-goog-signature'], true)) {
                return true;
            }
        }

        $hosts = collect([config('app.url'), config('brand.website_url'), config('booking.host'), request()->getHost()])
            ->filter()->map(fn ($host) => strtolower(parse_url(str_contains($host, '://') ? $host : 'https://'.$host, PHP_URL_HOST) ?? ''))
            ->flatMap(fn ($host) => [preg_replace('/^www\./', '', $host), 'www.'.preg_replace('/^www\./', '', $host)])->unique();
        if (isset($parts['host']) && ! $hosts->contains(strtolower($parts['host']))) {
            return false;
        }

        $path = rawurldecode(rawurldecode($parts['path'] ?? ''));
        $segments = [];
        foreach (explode('/', str_replace('\\', '/', $path)) as $segment) {
            if ($segment === '..') {
                array_pop($segments);
            } elseif ($segment !== '' && $segment !== '.') {
                $segments[] = $segment;
            }
        }
        $path = '/'.implode('/', $segments);

        foreach (['appointments/secure', 'client-files', 'client-forms', 'waitlist-offers', 'manage-appointment',
            'communications/actions', 'staff-invitations', 'livewire', 'livewire-tmp', 'storage/livewire-tmp',
            'storage/private', 'businesses', 'admin', 'dashboard', 'app'] as $privatePath) {
            if ($path === '/'.$privatePath || str_starts_with($path, '/'.$privatePath.'/')) {
                return true;
            }
        }

        return false;
    }
}
