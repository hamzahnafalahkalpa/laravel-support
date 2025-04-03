<?php

namespace Hanafalah\LaravelSupport\Concerns\Support;

use Hanafalah\LaravelSupport\Resources\ImageProcessing\ViewImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

trait HasImageProcessing{
    protected $__filesystem_disk = 'public';

    public function driver(): string{
        return config('app.impersonate.storage.driver',$this->__filesystem_disk);
    }

    public function storagePath(string $path = ''){        
        return $path;
    }

    protected function getImageName(): string{
        return 'photo';
    }

    public function getImage(){
        return $this->{$this->getImageName()};
    }

    protected function getPhotoPath(? string $path = null): string{
        $path ??= 'GALLERIES';
        return $this->storagePath($path);
    }

    public function setFilesystemDisk(string $disk): self{
        $this->__filesystem_disk = $disk;
        return $this;
    }

    public function getViewPhotoResource(){
        return ViewImage::class;
    }

    public function getImageFile(? string $path = null){
        if (!$this->getImage()) abort(404);
        
        $disk    = $this->__filesystem_disk ?? $this->driver();
        $filePath = $this->getPhotoPath($path) . '/' . $this->getImage();
        if (!Storage::disk($disk)->exists($filePath))  abort(404);
        return response()->file(Storage::disk($disk)->path($filePath));
    }

    public function setupPhoto(string|UploadedFile|null $photo = null, ?string $path = null, string $filename = null): ?string{
        $current = $this->getImage() ?? null;

        if ($photo instanceof UploadedFile) {
            $filename ??= Str::orderedUuid();
            $ext        = $photo->getClientOriginalExtension();
            $filename  .= '.' . $ext;
            $photo->storePubliclyAs($this->getPhotoPath($path), $filename, [
                'disk' => $this->__filesystem_disk ?? $this->driver()
            ]);
            $result = $filename;
            $remove_current = true;
        } elseif (is_string($photo)) {
            $result = $photo;
        } else {
            $remove_current = true;
            $result = null;
        }
        if (isset($current, $remove_current)){
            $disk = $this->__filesystem_disk ?? $this->driver();
            Storage::disk($disk)->delete($this->getPhotoPath($path) . '/' . $current);
        }
        return $result;
    }
}