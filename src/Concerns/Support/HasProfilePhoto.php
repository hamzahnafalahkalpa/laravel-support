<?php

namespace Hanafalah\LaravelSupport\Concerns\Support;

use Hanafalah\LaravelSupport\Resources\ProfilePhoto\ViewPhotoResource;
use Illuminate\Http\UploadedFile;

trait HasProfilePhoto{
    use HasImageProcessing;

    public function getViewPhotoResource(){
        return ViewPhotoResource::class;
    }

    protected function getPhotoPath(? string $path = null): string{
        $path ??= 'PROFILES';
        return $this->storagePath($path);
    }

    public function getImageName(){
        return 'profile';
    }

    public function setProfilePhoto(string|UploadedFile|null $photo = null, ?string $path = null, string $filename = null): ?string{
        $path ??= 'PROFILES';
        return $this->setupPhoto($photo,$path,$filename);
    }

    public function getProfilePhoto(? string $path = null){
        $path ??= 'PROFILES';
        return $this->getImageFile($path);
    }
}