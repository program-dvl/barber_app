<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Domain\Marketing\Services\ArticlePublication;
use App\Filament\Resources\Articles\ArticleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditArticle extends EditRecord
{
    use HandlesArticleUploads;

    protected static string $resource = ArticleResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = app(ArticlePublication::class)->prepare($data);
        if (collect(['title', 'content', 'excerpt'])->contains(fn ($field) => ($data[$field] ?? null) !== $this->record->getAttribute($field))) {
            $data['materially_updated_at'] = now();
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
