<?php

namespace App\Filament\Resources\Articles\Pages;

use Illuminate\Validation\ValidationException;

trait HandlesArticleUploads
{
    public function _finishUpload($name, $tmpPath, $isMultiple, $append = true)
    {
        parent::_finishUpload($name, $tmpPath, $isMultiple, $append);

        if ($name === 'data.thumbnail' || str_starts_with($name, 'data.thumbnail.')) {
            $this->resetValidation('data.thumbnail');
        }
    }

    public function _uploadErrored($name, $errorsInJson, $isMultiple)
    {
        if ($name === 'data.thumbnail' || str_starts_with($name, 'data.thumbnail.')) {
            $this->dispatch('upload:errored', name: $name)->self();

            throw ValidationException::withMessages([
                'data.thumbnail' => 'The featured image could not be uploaded. Your text is still here. Save a draft to keep writing, or choose another JPG, PNG or WebP up to 5 MB.',
            ]);
        }

        return parent::_uploadErrored($name, $errorsInJson, $isMultiple);
    }
}
