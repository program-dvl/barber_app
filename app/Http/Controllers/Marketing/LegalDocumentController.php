<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Support\Legal\PrivacyNotice;
use App\Support\Seo\PublicPage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class LegalDocumentController extends Controller
{
    public function terms(): Response
    {
        return $this->render('terms');
    }

    public function privacy(Request $request, PrivacyNotice $notice)
    {
        $canonical = config('privacy.canonical_url');
        $host = parse_url($canonical, PHP_URL_HOST);
        if ($host && $request->getHost() === 'www.'.$host) {
            return redirect()->away($canonical, 301);
        }
        // A full visit preserves the policy's server-owned metadata when reached
        // from another page's Inertia link; the normal shared Vue shell still mounts.
        if ($request->header('X-Inertia')) {
            return Inertia::location($request->url());
        }

        $data = [
            ...$notice->content(),
            'published' => $notice->isPublished(),
            'seo' => [
                'title' => 'Clipperdesk Privacy Policy',
                'description' => 'How Clipperdesk collects, uses, shares and retains account, booking and billing information, including Google Sign-In, and how to request privacy rights.',
                'canonical' => $canonical,
                'site_name' => 'Clipperdesk',
                'locale' => 'en_CA',
            ],
        ];
        $content = view('legal.privacy-content', $data)->render();

        return PublicPage::render('PrivacyPolicy', [
            'content' => $content,
            'seo' => $data['seo'],
            // Keep legal-page identity and footer presentation scoped to this page.
            'legalNotice' => true,
        ])->rootView('legal.privacy')->withViewData([
            'content' => $content,
            'seo' => $data['seo'],
        ]);
    }

    public function refund(): Response
    {
        return $this->render('refund');
    }

    private function render(string $key): Response
    {
        $document = config("legal.{$key}");
        abort_unless(is_array($document), 404);

        $markdown = file_get_contents(resource_path('markdown/'.$document['file']));
        abort_if($markdown === false, 500, 'The legal review document could not be loaded.');
        $html = Str::markdown($markdown, ['allow_unsafe_links' => false]);
        foreach (['operator_name', 'operator_address', 'privacy_email'] as $field) {
            $html = str_replace('{{'.$field.'}}', e(config('privacy.'.$field)), $html);
        }
        $html = str_replace('{{trial_days}}', (string) (int) config('billing.trial_days'), $html);

        $related = collect($document['related'])
            ->map(fn (array $item): array => [
                'label' => $item['label'],
                'href' => route($item['route']),
            ])
            ->all();

        return PublicPage::render($document['component'], [
            $document['prop'] => $html,
            'document' => [
                ...collect($document)->except(['file', 'prop', 'component', 'route', 'related'])->all(),
                'canonical' => route($document['route']),
                'related' => $related,
            ],
            'seo' => [
                'title' => $document['page_title'],
                'description' => $document['summary'],
                'canonical' => route($document['route']),
            ],
        ]);
    }
}
