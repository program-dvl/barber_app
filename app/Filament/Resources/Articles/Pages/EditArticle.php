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
        return app(ArticlePublication::class)->prepare($data, auth()->user(), $this->record);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
