<?php

namespace Hanafalah\LaravelSupport\Concerns\Support;

use Hanafalah\LaravelSupport\Resources\FileProcessing\ViewFileProcessing;
use Hanafalah\LaravelSupport\Resources\ImageProcessing\ViewImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

trait HasFileUpload{
    protected $__filesystem_disk = 'public';

    public function encryptName(string $name){
        $name = Crypt::encryptString($name);
        return $this->base64url_encode($name);
    }

    public function decryptName(string $name){
        $name = $this->base64url_decode($name);
        return Crypt::decryptString($name);
    }

    private function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64url_decode($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    public function driver(): string{
        return config('filesystems.default',$this->__filesystem_disk);
        // return config('app.impersonate.storage.disk',$this->__filesystem_disk);
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
        $path ??= 'files';
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
        
        $disk     = $this->driver();
        $filePath = $this->getFilePath($path) . '/' . $this->getFile();
        if (!Storage::disk($disk)->exists($filePath))  abort(404);
        return response()->file(Storage::disk($disk)->path($filePath));
    }

    public function getFullUrl(?string $path = null): string{
        return Storage::disk($this->driver())->url($this->getFilePath() . '/' . $path ?? $this->getFile());
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
        $disk       = $this->driver();
        if ($file instanceof UploadedFile) {
            $originalName = $file->getClientOriginalName();
            if (
                Str::contains($originalName, '.part')
                && request()->has('chunk_index')
            ) {
                $this->uploadChunk($file, [
                    'upload_id'   => request()->id,
                    'chunk_index' => request()->chunk_index,
                    'key'         => $this->getFilePath($path).'/'.($filename ?? $originalName),
                ]);
                return null;
            }else{
                $data = $this->normalUploadFile($file, $file_path, $filename, $disk);
                $result = ltrim($data[0], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($data[2], DIRECTORY_SEPARATOR);
                $result = ltrim($result, '/');
                $remove_current = true;
            }
        } elseif (is_string($file) && Str::startsWith($file, 'data:')) {
            // === handle base64 ===
            [$meta, $fileBase64] = explode(',', $file, 2);
            $fileBase64 = base64_decode($fileBase64);

            // cari mime type & extension dari metadata
            preg_match('/^data:(.*?);base64$/', $meta, $matches);
            $mimeType   = $matches[1] ?? 'application/octet-stream';
            $extension  = explode('/', $mimeType)[1] ?? 'bin';

            $filename ??= Str::orderedUuid();
            $filename  .= '.' . $extension;
            $data = [
                $file_path.'/'.$filename, 
                $fileBase64
            ];
            
            $result = Storage::disk($disk)->put(...$data);
            // $result = $filename;
            $result = ltrim($file_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($filename, DIRECTORY_SEPARATOR);
            $result = ltrim($result, '/');
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

    protected function normalUploadFile(UploadedFile $file, ?string $file_path = null, ?string $filename = null, ?string $disk = null): array{
        if ($file->getError() !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE   => 'File terlalu besar (melebihi upload_max_filesize '.ini_get('upload_max_filesize').')',
                UPLOAD_ERR_FORM_SIZE  => 'File terlalu besar (melebihi batas HTML form MAX_FILE_SIZE)',
                UPLOAD_ERR_PARTIAL    => 'File hanya terupload sebagian',
                UPLOAD_ERR_NO_FILE    => 'Tidak ada file yang diupload',
            ];
            throw new \RuntimeException(
                $errorMessages[$file->getError()] ?? 'Upload file gagal (error code ' . $file->getError() . ')'
            );
        }
        $filename ??= Str::orderedUuid();
        $ext        = $file->getClientOriginalExtension();
        $filename  .= '.' . $ext;
        $data = [
            $file_path, 
            $file,
            $filename
        ];
        Storage::disk($disk)->putFileAs(...$data);
        return $data;
    }

    public function deleteFile(string $path, ?string $disk = null): void{
        $disk = $disk ?? $this->__filesystem_disk ?? $this->driver();
        if (Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
    }
}