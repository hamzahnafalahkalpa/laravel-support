<?php

namespace Hanafalah\LaravelSupport\Concerns\Support;

use Hanafalah\LaravelSupport\Resources\ProfilePhoto\ViewPhotoResource;
use Illuminate\Http\UploadedFile;

trait HasProfilePhoto{
    use HasImageProcessing;

    public function getViewFileResource(){
        return ViewPhotoResource::class;
    }

    protected function getFilePath(? string $path = null): string{
        $path ??= 'profiles';
        return $this->storagePath($path);
    }

    public function getFileNameAttribute(){
        return 'profile';
    }

    public function setProfilePhoto(string|UploadedFile|null $photo = null, ?string $path = null, ?string $filename = null): ?string{
        $path ??= 'profiles';
        return $this->setupFile($photo,$path,$filename);
    }

    public function getProfilePhoto(? string $path = null){
        $path ??= 'profiles';
        return $this->getStorageFile($path);
    }
}