<?php

namespace Hanafalah\LaravelSupport\Concerns\Support;

use Hanafalah\LaravelSupport\Resources\ImageProcessing\ViewImage;

trait HasImageProcessing{
    use HasFileUpload;

    protected function getFileNameAttribute(): string{
        return 'photo';
    }

    protected function getFilePath(? string $path = null): string{
        $path ??= 'GALLERIES';
        return $this->storagePath($path);
    }

    public function getViewFileResource(){
        return ViewImage::class;
    }
}