<?php

namespace Hanafalah\LaravelSupport\Models\Export;

use Hanafalah\LaravelSupport\Enums\Export\ExportStatus;
use Hanafalah\LaravelSupport\Models\BaseModel;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Export extends BaseModel
{
    use HasUlids, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'exports';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'user_id',
        'export_type',
        'reference_type',
        'reference_id',
        'status',
        'file_path',
        'file_name',
        'error_message',
        'metadata',
        'expires_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => ExportStatus::class,
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the reference that owns the export (polymorphic).
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user that created the export.
     */
    public function user(): BelongsTo
    {
        return $this->belongsToModel('User', 'user_id');
    }

    /**
     * Scope to filter exports by status.
     */
    public function scopeByStatus($query, ExportStatus $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get expired exports.
     */
    public function scopeExpired($query)
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<', now());
    }

    /**
     * Mark the export as processing.
     */
    public function markAsProcessing(): void
    {
        $this->update(['status' => ExportStatus::PROCESSING]);
    }

    /**
     * Mark the export as completed.
     */
    public function markAsCompleted(string $filePath, string $fileName): void
    {
        $this->update([
            'status' => ExportStatus::COMPLETED,
            'file_path' => $filePath,
            'file_name' => $fileName,
        ]);
    }

    /**
     * Mark the export as failed.
     */
    public function markAsFailed(\Throwable $exception): void
    {
        $this->update([
            'status' => ExportStatus::FAILED,
            'error_message' => $exception->getMessage(),
        ]);
    }

    /**
     * Check if the export is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === ExportStatus::COMPLETED;
    }

    /**
     * Check if the export is pending.
     */
    public function isPending(): bool
    {
        return $this->status === ExportStatus::PENDING;
    }

    /**
     * Check if the export is processing.
     */
    public function isProcessing(): bool
    {
        return $this->status === ExportStatus::PROCESSING;
    }

    /**
     * Check if the export failed.
     */
    public function isFailed(): bool
    {
        return $this->status === ExportStatus::FAILED;
    }

    /**
     * Check if the export file can be downloaded.
     */
    public function canDownload(): bool
    {
        return $this->isCompleted() &&
               $this->file_path &&
               Storage::disk('s3')->exists($this->file_path);
    }

    /**
     * Get a temporary download URL for the export file (S3 presigned URL).
     * Valid for 60 minutes by default.
     */
    public function getDownloadUrl(int $expirationMinutes = 60): ?string
    {
        if (!$this->file_path || !$this->canDownload()) {
            return null;
        }

        return Storage::disk('s3')->temporaryUrl(
            $this->file_path,
            now()->addMinutes($expirationMinutes)
        );
    }

    /**
     * Get the S3 file path.
     */
    public function getS3Path(): ?string
    {
        return $this->file_path;
    }

    /**
     * Delete the export file from S3.
     */
    public function deleteFile(): bool
    {
        if (!$this->file_path) {
            return false;
        }

        return Storage::disk('s3')->delete($this->file_path);
    }
}
