<?php

namespace Hanafalah\LaravelSupport\Concerns\Support;

use Hanafalah\LaravelSupport\Models\Elasticsearch\ElasticsearchLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Trait for logging Elasticsearch operations to database.
 *
 * Handles two scenarios:
 * 1. Dashboard metrics: Updates existing log record (uses ES document ID as log ID)
 * 2. Reporting data: Creates new log records for each document
 */
trait HasElasticsearchLog
{
    use HasRequestData;
    /**
     * Log Elasticsearch bulk operations to database.
     *
     * @param array $bulks The bulk operations array
     * @param array $response The Elasticsearch response
     * @return void
     */
    protected function logElasticsearchOperations(array $bulks, array $response): void
    {
        try {
            $items = $response['items'] ?? [];
            $itemIndex = 0;

            foreach ($bulks as $bulk) {
                // Skip non-array items (they are document bodies)
                if (!isset($bulk['index']) && !isset($bulk['delete']) && !isset($bulk['update'])) {
                    continue;
                }

                $action = array_key_first($bulk);
                $actionData = $bulk[$action];
                $indexName = $actionData['_index'] ?? null;
                $documentId = $actionData['_id'] ?? null;

                if (!$indexName || !$documentId) {
                    continue;
                }

                // Get operation result from response
                $operationResult = $items[$itemIndex] ?? null;
                $resultData = $operationResult[$action] ?? [];
                $itemIndex++;

                // Only log successful operations
                if (isset($resultData['error'])) {
                    continue;
                }

                $this->createOrUpdateLog($indexName, $documentId, $action, $resultData);
            }
        } catch (\Throwable $e) {
            Log::channel('elasticsearch')->warning('Failed to log ES operations to database', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create or update a log record based on index type.
     *
     * @param string $indexName
     * @param string $documentId
     * @param string $action
     * @param array $resultData
     * @return void
     */
    protected function createOrUpdateLog(string $indexName, string $documentId, string $action, array $resultData): void
    {
        $model = $this->getElasticsearchLogModel();
        $isDashboard = $this->isDashboardIndex($indexName);

        // For dashboard, use a consistent log ID based on index + document ID
        // This ensures updates don't create new records
        $logId = $isDashboard
            ? $this->generateDashboardLogId($indexName, $documentId)
            : (string) Str::ulid();

        $now = now();

        $data = [
            'name' => $this->extractIndexType($indexName),
            'index_name' => $indexName,
            'document_id' => $documentId,
            'synced_at' => $now,
            'action' => $action,
            'result' => $resultData['result'] ?? null,
            'version' => $resultData['_version'] ?? null,
            'is_dashboard' => $isDashboard,
        ];
        $model = app(config('app.contracts.ElasticsearchLog'))->prepareStoreElasticsearchLog(
            $this->requestDTO(config('app.contracts.ElasticsearchLogData'),
                array_merge($data, ['id' => $logId,'updated_at' => $now])
            )
        );
    }

    /**
     * Check if the index is a dashboard index (should update existing records).
     *
     * @param string $indexName
     * @return bool
     */
    protected function isDashboardIndex(string $indexName): bool
    {
        $prefixes = config('elasticsearch.logging.dashboard_prefixes', [
            'dashboard-metrics',
            'dashboard',
        ]);

        foreach ($prefixes as $prefix) {
            if (Str::contains($indexName, $prefix)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Generate a consistent log ID for dashboard entries.
     * This ensures the same ES document always maps to the same log record.
     *
     * @param string $indexName
     * @param string $documentId
     * @return string
     */
    protected function generateDashboardLogId(string $indexName, string $documentId): string
    {
        // Create a deterministic ULID-like ID from index + document ID
        // We use a hash to create a consistent ID that can be used for updateOrCreate
        $hash = md5($indexName . ':' . $documentId);

        // Convert to ULID format (26 chars, base32)
        // Take first 26 chars of base32 encoded hash
        $base32 = strtoupper(substr(base_convert($hash, 16, 32), 0, 26));

        // Pad if necessary
        return substr(str_pad($base32, 26, '0', STR_PAD_LEFT), 0, 26);
    }

    /**
     * Extract the index type from the full index name.
     * e.g., "development.dashboard-metrics-daily" -> "dashboard-metrics-daily"
     *
     * @param string $indexName
     * @return string
     */
    protected function extractIndexType(string $indexName): string
    {
        $parts = explode('.', $indexName);
        return end($parts);
    }

    /**
     * Get the ElasticsearchLog model class.
     *
     * @return string
     */
    protected function getElasticsearchLogModel(): string
    {
        return config('database.models.ElasticsearchLog', ElasticsearchLog::class);
    }

    /**
     * Log a single Elasticsearch operation.
     *
     * @param string $indexName
     * @param string $documentId
     * @param string $action
     * @param array $data Additional data to log
     * @return void
     */
    public function logSingleOperation(string $indexName, string $documentId, string $action, array $data = []): void
    {
        try {
            $this->createOrUpdateLog($indexName, $documentId, $action, $data);
        } catch (\Throwable $e) {
            Log::channel('elasticsearch')->warning('Failed to log single ES operation', [
                'error' => $e->getMessage(),
                'index' => $indexName,
                'document_id' => $documentId,
            ]);
        }
    }
}
