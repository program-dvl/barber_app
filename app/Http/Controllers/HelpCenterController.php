<?php

namespace App\Http\Controllers;

use App\Domain\HelpCenter\HelpCatalog;
use App\Domain\HelpCenter\HelpSearch;
use App\Domain\PlatformAccess\Services\WorkspaceAccessService;
use App\Support\Seo\PublicPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class HelpCenterController extends Controller
{
    public function __construct(private readonly HelpCatalog $catalog) {}

    public function index(Request $request, HelpSearch $search): Response
    {
        $query = $this->query($request);
        if ($query !== '') {
            $request->attributes->set('force_noindex', true);
        }

        return $this->render('Help/Index', [
            'categories' => $this->catalog->categories(),
            'articles' => $this->catalog->summaries(),
            'featured' => array_slice($this->catalog->summaries('getting-started'), 0, 4),
            'query' => $query,
            'results' => $query === '' ? [] : $search->query($query),
        ], 'Help Center | ClipperDesk', 'Find step-by-step ClipperDesk guides for business setup, bookings, team, clients, sales and billing.', route('help.index'));
    }

    public function category(string $category): Response
    {
        $record = collect($this->catalog->categories())->firstWhere('slug', $category);
        abort_unless($record, 404);

        return $this->render('Help/Category', [
            'category' => $record,
            'articles' => $this->catalog->summaries($category),
        ], $record['title'].' help | ClipperDesk', 'Explore step-by-step ClipperDesk guidance for '.mb_strtolower($record['title']).'. '.$record['description'], route('help.category', $category));
    }

    public function article(string $slug): Response
    {
        $article = $this->catalog->find($slug);
        abort_unless($article, 404);

        return $this->render('Help/Article', [
            'article' => $article,
            'category' => collect($this->catalog->categories())->firstWhere('slug', $article['category']),
            'related' => $article['related'],
        ], $article['title'].' | ClipperDesk Help', $article['summary'], route('help.article', $slug));
    }

    public function search(Request $request, HelpSearch $search): JsonResponse
    {
        $query = $this->query($request);

        return response()->json(['query' => $query, 'results' => $search->query($query, 8)])
            ->header('Cache-Control', 'private, no-store');
    }

    public function open(Request $request, string $action, WorkspaceAccessService $access): Response|RedirectResponse
    {
        $definition = config('help.actions.'.$action);
        abort_unless(is_array($definition), 404);
        if (! isset($definition['feature'])) {
            return redirect()->route($definition['route']);
        }
        $workspaces = $request->user()->memberships()->active()->with('business')->orderBy('id')->get()
            ->filter(fn ($membership) => $membership->isActive())
            ->map(function ($membership) use ($access, $definition) {
                $decision = $access->decide($membership->business, $membership, $definition['feature']);

                return [
                    'public_id' => $membership->business->public_id,
                    'name' => $membership->business->name,
                    'href' => $decision['allowed'] ? route($definition['route'], $membership->business) : null,
                    'url' => $decision['allowed'] ? route($definition['route'], $membership->business) : null,
                    'allowed' => $decision['allowed'],
                    'status' => $decision['status'],
                    'reason' => $decision['reason'],
                ];
            })->values();
        $selected = $request->query('business');
        if (is_string($selected) && $selected !== '') {
            $workspace = $workspaces->firstWhere('public_id', $selected);
            abort_unless($workspace, 404);
            if ($workspace['allowed']) {
                return redirect()->to($workspace['href']);
            }
        } elseif ($workspaces->count() === 1 && $workspaces->first()['allowed']) {
            return redirect()->to($workspaces->first()['href']);
        }

        return Inertia::render('Help/Workspaces', [
            'action' => ['key' => $action, 'label' => $definition['label']],
            'workspaces' => $workspaces->all(),
            'message' => $workspaces->isEmpty() ? 'No active business workspace is available for this account. Ask your business owner to check your invitation or access.' : null,
        ]);
    }

    public function feedback(Request $request, string $slug): JsonResponse
    {
        $article = $this->catalog->find($slug);
        abort_unless($article, 404);
        $data = $request->validate(['helpful' => ['required', 'boolean']]);
        // Only one opaque session key per article revision; no account, business,
        // IP address, question text or browser details are collected.
        $visitor = $request->session()->get('help_feedback_visitor');
        if (! is_string($visitor)) {
            $visitor = Str::random(64);
            $request->session()->put('help_feedback_visitor', $visitor);
        }
        DB::table('help_article_feedback')->upsert([[
            'article_slug' => $slug,
            'revision' => $article['updated_at'],
            'visitor_hash' => hash_hmac('sha256', $visitor, config('app.key')),
            'helpful' => (bool) $data['helpful'],
            'created_at' => now(),
            'updated_at' => now(),
        ]], ['article_slug', 'revision', 'visitor_hash'], ['helpful', 'updated_at']);

        return response()->json(['saved' => true])->header('Cache-Control', 'private, no-store');
    }

    private function query(Request $request): string
    {
        $query = $request->query('q', '');

        return is_string($query) ? mb_substr(trim($query), 0, 180) : '';
    }

    private function render(string $component, array $props, string $title, string $description, string $canonical): Response
    {
        $title = mb_strlen($title) > 70 ? rtrim(Str::limit($title, 69, '')).'…' : $title;
        if (mb_strlen($description) < 80) {
            $description .= ' Follow the steps in your ClipperDesk workspace.';
        }
        $description = mb_strlen($description) > 180 ? rtrim(Str::limit($description, 179, '')).'…' : $description;
        $props['seo'] = compact('title', 'description', 'canonical');

        return PublicPage::render($component, $props)->rootView('help.root')->withViewData([
            'helpComponent' => $component,
        ]);
    }
}
