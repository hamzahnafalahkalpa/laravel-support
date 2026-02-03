<?php

namespace Hanafalah\LaravelSupport\Commands;

use Hanafalah\LaravelSupport\Models\Export\Export;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupExpiredExportsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exports:cleanup
                            {--days= : Override default expiration (uses expires_at by default)}
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired export files from S3 and database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $daysOverride = $this->option('days');

        $this->info('Starting expired exports cleanup...');

        // Build query for expired exports
        $query = Export::query()
            ->whereNotNull('file_path')
            ->where('status', \Hanafalah\LaravelSupport\Enums\Export\ExportStatus::COMPLETED);

        if ($daysOverride) {
            // Override: delete exports older than X days
            $query->where('created_at', '<', now()->subDays((int) $daysOverride));
            $this->info("Using override: deleting exports older than {$daysOverride} days");
        } else {
            // Default: use expires_at field
            $query->expired();
            $this->info('Using expires_at field for expiration check');
        }

        $expiredExports = $query->get();
        $totalCount = $expiredExports->count();

        if ($totalCount === 0) {
            $this->info('No expired exports found.');
            return Command::SUCCESS;
        }

        $this->info("Found {$totalCount} expired exports to clean up.");

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No files will be deleted.');
            $this->table(
                ['ID', 'Type', 'File Path', 'Expires At', 'Created At'],
                $expiredExports->map(fn ($export) => [
                    $export->id,
                    $export->export_type,
                    $export->file_path,
                    $export->expires_at?->format('Y-m-d H:i:s'),
                    $export->created_at?->format('Y-m-d H:i:s'),
                ])->toArray()
            );
            return Command::SUCCESS;
        }

        $deletedCount = 0;
        $failedCount = 0;
        $progressBar = $this->output->createProgressBar($totalCount);

        foreach ($expiredExports as $export) {
            try {
                // Delete file from S3
                $export->deleteFile();

                // Soft delete the export record
                $export->delete();

                $deletedCount++;

                Log::info('Expired export cleaned up', [
                    'export_id' => $export->id,
                    'file_path' => $export->file_path,
                ]);
            } catch (\Throwable $e) {
                $failedCount++;

                Log::error('Failed to cleanup expired export', [
                    'export_id' => $export->id,
                    'file_path' => $export->file_path,
                    'error' => $e->getMessage(),
                ]);

                $this->error("Failed to delete export {$export->id}: {$e->getMessage()}");
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        $this->info("Cleanup completed: {$deletedCount} deleted, {$failedCount} failed.");

        return $failedCount > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
