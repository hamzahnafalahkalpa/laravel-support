<?php

namespace Hanafalah\LaravelSupport\Concerns\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

trait HasMediaLibrary
{
    use HasFileUpload;

    public function setupMediaFiles(array $files, string $collection = 'default'): Collection
    {
        $model = $this->getReferenceModel();

        if (!method_exists($model, 'getMedia')) {
            throw new \Exception('Model ' . get_class($model) . ' must use InteractsWithMedia (Spatie)');
        }

        $existingMedia = $model->getMedia($collection)->keyBy('file_name');
        $newMedia = collect();

        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $media = $model
                    ->addMedia($file)
                    ->toMediaCollection($collection);

                $newMedia->put($media->file_name, $media);
            } elseif (is_string($file)) {
                if ($existingMedia->has($file)) {
                    $newMedia->put($file, $existingMedia->get($file));
                }
            }
        }

        if ($this->shouldDeleteUnusedFiles()) {
            $unused = $existingMedia->keys()->diff($newMedia->keys());
            foreach ($unused as $fileName) {
                $existingMedia->get($fileName)?->delete();
            }
        }

        return $newMedia->values();
    }

    public function shouldDeleteUnusedFiles(): bool
    {
        return false; // defaultnya aman, karena bisa dipakai untuk gallery
    }
}
