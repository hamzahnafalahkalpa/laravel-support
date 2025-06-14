<?php

namespace Hanafalah\LaravelSupport\Concerns\Support;

use Hanafalah\LaravelSupport\Resources\FileProcessing\ViewFileProcessing;
use Hanafalah\LaravelSupport\Resources\ImageProcessing\ViewImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

trait HasFileUpload{
    protected $__filesystem_disk = 'public';

    public function driver(): string{
        return config('app.impersonate.storage.driver',$this->__filesystem_disk);
    }

    public function storagePath(string $path = ''){        
        return $path;
    }

    protected function getFileNameAttribute(): string|callable{
        return 'file';
    }

    public function getReferenceModel(): mixed{
        return $this;
    }

    public function getFile(){
        $attribute = $this->getFileNameAttribute();
        if (is_callable($attribute) && !is_string($attribute)){
            return $attribute($this);
        }else{
            return $this->{$attribute} ?? null;
        }
    }

    protected function getFilePath(? string $path = null): string{
        $path ??= 'FILES';
        return $this->storagePath($path);
    }

    public function setFilesystemDisk(string $disk): self{
        $this->__filesystem_disk = $disk;
        return $this;
    }

    public function getViewFileResource(){
        return ViewFileProcessing::class;
    }

    public function getStorageFile(? string $path = null){
        if (!$this->getFile()) abort(404);
        
        $disk     = $this->__filesystem_disk ?? $this->driver();
        $filePath = $this->getFilePath($path) . '/' . $this->getFile();
        if (!Storage::disk($disk)->exists($filePath))  abort(404);
        return response()->file(Storage::disk($disk)->path($filePath));
    }

    public function getFullUrl(?string $path = null): string{
        return Storage::disk($this->__filesystem_disk ?? $this->driver())->url($this->getFilePath() . '/' . $path ?? $this->getFile());
    }

    public function setupFiles(array $files, ?string $path = null): array{
        if (!method_exists($this, 'getCurrentFiles')) {
            throw new \Exception('Method getCurrentFiles() must be implemented in ' . get_class($this));
        }
    
        $oldFiles = collect($this->getCurrentFiles());
        $newFiles = collect($files)->map(function ($file, $key) use ($path) {
            $filename = is_string($key) ? $key : null;
            return $this->setupFile($file, $path, $filename);
        });
        
        $unusedFiles = $oldFiles->diff($newFiles->filter());
        foreach ($unusedFiles as $file) {
            $this->deleteFile($this->getFilePath($path).'/'.$file);
        }
    
        return $newFiles->values()->all();
    }

    public function setupFile(string|UploadedFile|null $file = null, ?string $path = null, ?string $filename = null): ?string{
        $current    = $this->getFile() ?? null;
        $file_path  = $this->getFilePath($path);
        $disk       = $this->__filesystem_disk ?? $this->driver();
        if ($file instanceof UploadedFile) {
            $filename ??= Str::orderedUuid();
            $ext        = $file->getClientOriginalExtension();
            $filename  .= '.' . $ext;
            $file->storePubliclyAs($file_path, $filename, [
                'disk' => $disk
            ]);
            $result = $filename;
            $remove_current = true;
        } elseif (is_string($file)) {
            $result = $file;
        } else {
            $remove_current = true;
            $result = null;
        }
        if (isset($current, $remove_current)){
            $this->deleteFile($this->getFilePath($path) . '/' . $current);
        }
        return $result;
    }

    public function deleteFile(string $path, ?string $disk = null): void{
        $disk = $disk ?? $this->__filesystem_disk ?? $this->driver();
        if (Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
    }
}