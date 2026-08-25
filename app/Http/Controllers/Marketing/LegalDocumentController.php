<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class LegalDocumentController extends Controller
{
    public function terms(): Response
    {
        return $this->render('terms');
    }

    public function privacy(): Response
    {
        return $this->render('privacy');
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

        $related = collect($document['related'])
            ->map(fn (array $item): array => [
                'label' => $item['label'],
                'href' => route($item['route']),
            ])
            ->all();

        return Inertia::render($document['component'], [
            $document['prop'] => Str::markdown($markdown),
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
