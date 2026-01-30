<?php

namespace Hanafalah\LaravelSupport\Jobs;

use Hanafalah\LaravelSupport\Models\Export\Export;
use Hanafalah\MicroTenant\Facades\MicroTenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessExportJob implements ShouldQueue
{
    use Queueable, SerializesModels, InteractsWithQueue;

    /**
     * Number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 300;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array
     */
    public $backoff = [10, 30, 60];

    /**
     * The export ID to process.
     *
     * @var string
     */
    protected string $exportId;

    /**
     * The tenant ID for multi-tenant isolation.
     *
     * @var int
     */
    protected int $tenantId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $exportId, int $tenantId)
    {
        $this->exportId = $exportId;
        $this->tenantId = $tenantId;
        $this->onQueue('export');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // CRITICAL: Set tenant context first for multi-tenant isolation
            MicroTenant::tenantImpersonate($this->tenantId);

            // Load export record
            $export = Export::findOrFail($this->exportId);

            // Mark as processing
            $export->markAsProcessing();

            // Resolve export class from configuration
            $exportClass = config("module-patient.exports.{$export->export_type}");

            if (!$exportClass) {
                throw new \Exception("Export class not found for type: {$export->export_type}");
            }

            if (!class_exists($exportClass)) {
                throw new \Exception("Export class does not exist: {$exportClass}");
            }

            // Get the reference model (e.g., VisitRegistration)
            $reference = $export->reference;

            if (!$reference) {
                throw new \Exception("Export reference not found for export ID: {$this->exportId}");
            }

            // Instantiate export handler
            $exporter = new $exportClass($reference);

            // Generate PDF and get file path
            $filePath = $exporter->generate($export);

            // Extract filename from path
            $fileName = basename($filePath);

            // Mark as completed
            $export->markAsCompleted($filePath, $fileName);

            Log::info("Export completed successfully", [
                'export_id' => $this->exportId,
                'tenant_id' => $this->tenantId,
                'export_type' => $export->export_type,
                'file_path' => $filePath,
            ]);

        } catch (\Throwable $e) {
            // Mark export as failed
            MicroTenant::tenantImpersonate($this->tenantId);
            $export = Export::find($this->exportId);

            if ($export) {
                $export->markAsFailed($e);
            }

            Log::error("Export job failed", [
                'export_id' => $this->exportId,
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Export job failed after all retries", [
            'export_id' => $this->exportId,
            'tenant_id' => $this->tenantId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Ensure export is marked as failed
        try {
            MicroTenant::tenantImpersonate($this->tenantId);
            $export = Export::find($this->exportId);

            if ($export) {
                $export->markAsFailed($exception);
            }
        } catch (\Throwable $e) {
            Log::error("Failed to mark export as failed", [
                'export_id' => $this->exportId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
