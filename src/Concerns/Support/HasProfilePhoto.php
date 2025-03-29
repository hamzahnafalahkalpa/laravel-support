<?php

namespace Hanafalah\LaravelSupport\Concerns\Support;

use Hanafalah\LaravelSupport\Resources\ProfilePhoto\ViewPhotoResource;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait HasProfilePhoto{
    protected $__filesystem_disk = 'public';

    public function driver(): string{
        return config('app.impersonate.storage.driver',$this->__filesystem_disk);
    }

    public function storagePath(string $path = ''){
        return $path;
    }

    public function setFilesystemDisk(string $disk): self{
        $this->__filesystem_disk = $disk;
        return $this;
    }

    public function getViewPhotoResource(){
        return ViewPhotoResource::class;
    }

    protected function getProfilePhotoPath(string $path = 'PROFILES'): string{
        return $this->storagePath($path);
    }

    public function setProfilePhoto(string|UploadedFile|null $profile = null, string $path = 'PROFILES', string $filename = null): ?string{
        $current = $this->profile ?? null;

        if ($profile instanceof UploadedFile) {
            $filename ??= Str::orderedUuid();
            $ext  = $profile->getClientOriginalExtension();
            $filename .= '.' . $ext;
            $profile->storePubliclyAs($this->getProfilePhotoPath($path), $filename, [
                'disk' => $this->__filesystem_disk ?? $this->driver()
            ]);
            $this->profile = $filename;
            $remove_current = true;
        } elseif (is_string($profile)) {
            $this->profile = $profile;
        } else {
            $remove_current = true;
            $this->profile = null;
        }
        if (isset($current, $remove_current)){
            $disk = $this->__filesystem_disk ?? $this->driver();
            Storage::disk($disk)->delete($this->getProfilePhotoPath($path) . '/' . $current);
        }
        return $this->profile;
    }
}