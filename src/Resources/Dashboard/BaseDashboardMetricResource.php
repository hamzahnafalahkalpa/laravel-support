<?php

namespace Hanafalah\LaravelSupport\Resources\Dashboard;

/**
 * Abstract base class for dashboard metric resources.
 *
 * Merges minimal ES data with presentation definitions to produce
 * full frontend-ready metric data.
 */
abstract class BaseDashboardMetricResource
{
    /**
     * Get metric definitions for the given period type.
     *
     * @param ?string $periodType The period type (daily, weekly, monthly, yearly)
     * @return array<string, array> Map of metric ID to presentation data
     */
    abstract protected function getDefinitions(?string $periodType = null): array;

    /**
     * Transform ES data by merging with presentation definitions.
     *
     * @param array $esData Array of ES metric items
     * @param string $periodType The period type
     * @return array Transformed data with presentation fields
     */
    public function transform(array $esData, ?string $periodType = null): array
    {
        $definitions = $this->getDefinitions($periodType);
        $result = [];
        if ($this->isAssocArray($esData)){
            $result = array_merge($esData,$definitions);
        }else{
            foreach ($esData as $item) {
                $id = $this->normalizeMetricId($item['id'] ?? '');
                if (isset($definitions[$id])) {
                    $result[] = $this->mergeWithPresentation($item, $definitions[$id]);
                } else {
                    // Return item as-is if no definition found
                    $result[] = $item;
                }
            }
        }

        return $result;
    }

    protected function isAssocArray(array $array): bool
    {
        if ($array === []) {
            return false; // treat empty as non-assoc (adjust if needed)
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Merge ES data with presentation definition.
     *
     * Uses previous_count from ES data to calculate change, change_type, percentage_change.
     *
     * @param array $item ES metric data (must contain count and previous_count)
     * @param array $definition Presentation definition
     * @return array Merged data
     */
    protected function mergeWithPresentation(array $item, array $definition): array
    {
        $count = (float) ($item['count'] ?? 0);
        $previousCount = (float) ($item['previous_count'] ?? 0);

        // Calculate change from count and previous_count
        $change = $count - $previousCount;

        return array_merge($definition, [
            'id' => $item['id'] ?? $definition['id'],
            'count' => $count,
            'previous_count' => $previousCount,
            'change' => abs($change),
            'change_type' => $this->calculateChangeType($change),
            'percentage_change' => $this->calculatePercentageChange($count, $previousCount),
            'created_at' => $item['created_at'] ?? null,
            'updated_at' => $item['updated_at'] ?? null,
        ]);
    }

    /**
     * Calculate change type based on change value.
     *
     * @param float $change The change value (can be negative)
     * @return string 'increase', 'decrease', or 'neutral'
     */
    protected function calculateChangeType(float $change): string
    {
        if ($change > 0) {
            return 'increase';
        } elseif ($change < 0) {
            return 'decrease';
        }

        return 'neutral';
    }

    /**
     * Calculate percentage change from current count and previous count.
     *
     * @param float $count Current count
     * @param float $previousCount Previous period's count
     * @return float Percentage change
     */
    protected function calculatePercentageChange(float $count, float $previousCount): float
    {
        if ($previousCount == 0) {
            return $count > 0 ? 100.0 : 0.0;
        }

        $change = $count - $previousCount;
        return round(($change / abs($previousCount)) * 100, 2);
    }

    /**
     * Normalize metric ID to snake_case.
     *
     * @param string $id The metric ID
     * @return string Normalized ID
     */
    protected function normalizeMetricId(string $id): string
    {
        // Convert kebab-case to snake_case
        return str_replace('-', '_', $id);
    }

    /**
     * Get change label based on period type.
     *
     * Override this method in implementations for localized labels.
     *
     * @param string $periodType The period type
     * @return string The change label
     */
    protected function getChangeLabel(string $periodType): string
    {
        return match ($periodType) {
            'daily' => 'From yesterday',
            'weekly' => 'From last week',
            'monthly' => 'From last month',
            'yearly' => 'From last year',
            default => 'From previous period'
        };
    }
}
