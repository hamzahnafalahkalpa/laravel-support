<?php

namespace Hanafalah\LaravelSupport\Concerns\Support;

use Illuminate\Http\UploadedFile;

trait HasProfilePhoto{
    public $profile;

    public function driver(): string{
        return config('app.impersonate.storage.driver','local');
    }

    public function storagePath(string $path = ''){
        return \storage_path($path);
    }

    protected function getProfilePhotoPath(string $path = 'PROFILES'): string{
        return $this->storagePath($path);
    }

    public function setProfilePhoto(string|UploadedFile|null $profile = null,string $path = '', string $filename = null): mixed{
        if ($profile instanceof UploadedFile){
            $filename ??= $profile->getClientOriginalName();
            $ext           = $profile->getClientOriginalExtension();
            $filename_ext  = $filename.'.'.$ext;
            $this->profile = $profile->storeAs($this->getProfilePhotoPath($path),$filename_ext);
        }elseif(!is_string($profile)){
            $this->profile = null;
        }
        return $this->profile;
    }
}