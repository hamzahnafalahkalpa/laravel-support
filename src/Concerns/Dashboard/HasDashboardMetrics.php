<?php

namespace Hanafalah\LaravelSupport\Concerns\Dashboard;

/**
 * Base trait with generic helpers for dashboard metrics.
 *
 * Provides common utilities without domain-specific content.
 */
trait HasDashboardMetrics
{
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

    /**
     * Normalize metric ID to snake_case.
     *
     * Converts kebab-case and camelCase to snake_case.
     *
     * @param string $id The metric ID
     * @return string Normalized ID in snake_case
     */
    protected function normalizeMetricId(string $id): string
    {
        // First convert kebab-case to spaces
        $id = str_replace('-', ' ', $id);

        // Then convert camelCase to spaces
        $id = preg_replace('/([a-z])([A-Z])/', '$1 $2', $id);

        // Convert to lowercase and replace spaces with underscores
        return str_replace(' ', '_', strtolower($id));
    }

    /**
     * Calculate change type based on change value.
     *
     * @param float $change The change value
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
     * Calculate percentage change.
     *
     * @param float $count Current count
     * @param float $change Change value
     * @return float Percentage change
     */
    protected function calculatePercentageChange(float $count, float $change): float
    {
        $previousValue = $count - $change;

        if ($previousValue == 0) {
            return $change > 0 ? 100.0 : 0.0;
        }

        return round(($change / abs($previousValue)) * 100, 2);
    }

    /**
     * Create a minimal ES metric item structure.
     *
     * @param string $id Metric identifier
     * @param float $count Current count value
     * @param float $change Change from previous period
     * @return array ES-ready metric structure
     */
    protected function createEsMetricItem(string $id, float $count = 0, float $change = 0): array
    {
        return [
            'id' => $this->normalizeMetricId($id),
            'count' => $count,
            'change' => $change,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Convert all keys in an array to snake_case.
     *
     * @param array $data The data array
     * @return array Data with snake_case keys
     */
    protected function convertKeysToSnakeCase(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $snakeKey = $this->normalizeMetricId($key);

            if (is_array($value)) {
                $result[$snakeKey] = $this->convertKeysToSnakeCase($value);
            } else {
                $result[$snakeKey] = $value;
            }
        }

        return $result;
    }
}
