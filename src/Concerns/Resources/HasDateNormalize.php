<?php

namespace Hanafalah\LaravelSupport\Concerns\Resources;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Hanafalah\LaravelSupport\Concerns\Support\HasArray;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait for normalizing datetime values in JSON columns (props, prop_activity, etc.)
 *
 * This trait handles timezone conversion for datetime values stored in JSON columns,
 * which cannot use Laravel's cast system.
 *
 * Octane-safe: Uses per-request config('app.client_timezone') set by middleware.
 *
 * Use cases:
 * - Datetime in props column: props->setting->last_login
 * - Activity timestamps: prop_activity->adm_visit->adm_start->at
 * - Nested datetime values in JSON structures
 */
trait HasDateNormalize
{
    use HasArray;

    /**
     * Track if normalization has already been run to prevent infinite loops.
     *
     * @var bool
     */
    private $__normalized = false;

    /**
     * Normalize all datetime values in the model.
     *
     * Converts datetime from UTC (database) to workspace timezone (API output).
     * Only runs if client_timezone is set (from workspace settings).
     *
     * @return void
     */
    public function normalize(): void
    {
        // Prevent infinite loops - only normalize once
        if ($this->__normalized) {
            return;
        }

        // Skip if no client timezone or not a model
        if (!$this->shouldNormalize()) {
            return;
        }

        // Mark as normalized
        $this->__normalized = true;

        // Normalize regular datetime casts and props fields
        try {
            $dates = $this->filterDates();
            foreach ($dates as $key => $cast) {
                if (Str::contains($cast, 'props->')) {
                    $this->dateAsProp($cast);
                } else {
                    // Regular field (for backward compatibility with old casts)
                    // Skip if the field doesn't exist in attributes
                    $attributes = $this->resource->getAttributes();
                    if (isset($attributes[$key])) {
                        $value = $attributes[$key];
                        if ($value instanceof \Carbon\Carbon) {
                            $this->resource->setAttribute($key, $this->dateAsCarbon($value));
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // Silently skip if there's an error during date filtering
            // This prevents breaking the entire response
        }

        // Normalize prop_activity timestamps
        $attributes = $this->resource->getAttributes();
        if (isset($attributes['prop_activity'])) {
            $this->normalizeActivity();
        }
    }

    /**
     * Check if normalization should run.
     *
     * @return bool
     */
    protected function shouldNormalize(): bool
    {
        return config('app.client_timezone') !== null && $this->resource instanceof Model;
    }

    /**
     * Get the client timezone for this request.
     *
     * @return string
     */
    protected function getClientTimezone(): string
    {
        return config('app.client_timezone', 'UTC');
    }

    /**
     * Recursively access and convert datetime in nested structures.
     *
     * @param  mixed  $source
     * @param  array  $pathParts
     * @return void
     */
    private function accessDataRecursive(&$source, array $pathParts): void
    {
        if (empty($pathParts)) {
            // Base case: convert the datetime
            if ($this->isValidDateString($source)) {
                $this->convertUtcToClientTimezone($source);
            }
            return;
        }

        $part = array_shift($pathParts);

        // Navigate to next level
        if (\is_object($source)) {
            if (isset($source->{$part})) {
                $this->accessDataRecursive($source->{$part}, $pathParts);
            }
        } elseif (\is_array($source)) {
            if (isset($source[$part])) {
                $this->accessDataRecursive($source[$part], $pathParts);
            }
        }
    }

    /**
     * Filter date/datetime casts from model.
     *
     * @return array
     */
    private function filterDates(): array
    {
        $dates = array_filter($this->resource->getCasts(), function ($cast) {
            // Only handle old-style casts (not TimezonedDateTime)
            return in_array($cast, ['date', 'datetime', 'immutable_date', 'immutable_datetime']);
        });

        // Include props query fields if available
        if (\method_exists($this->resource, 'getPropsQuery')) {
            $propsQuery = $this->resource->getPropsQuery();
            $propsQuery = array_intersect_key($propsQuery, $dates);
            $dates = $this->mergeArray($dates, $propsQuery);
        }

        return $dates;
    }

    /**
     * Normalize activity timestamps in prop_activity.
     *
     * Handles structures like:
     * - prop_activity->adm_visit->adm_start->at
     * - prop_activity->life_cycle[0]->adm_start->at
     *
     * @return void
     */
    private function normalizeActivity(): void
    {
        // Get raw attribute value to avoid triggering accessors/infinite loops
        $attributes = $this->resource->getAttributes();

        if (!isset($attributes['prop_activity'])) {
            return;
        }

        $prop_activity = $attributes['prop_activity'];

        // If it's a JSON string, decode it
        if (is_string($prop_activity)) {
            $prop_activity = json_decode($prop_activity, true);
        }

        if (!is_array($prop_activity)) {
            return;
        }

        // Process each activity group
        foreach ($prop_activity as $key => &$value) {
            if ($this->isAssociative($value)) {
                // Associative array: e.g., adm_visit->adm_start
                $this->processActivityGroup($value);
            } elseif (is_array($value)) {
                // Indexed array: e.g., life_cycle[0], life_cycle[1]
                foreach ($value as &$item) {
                    if (is_array($item)) {
                        $this->processActivityGroup($item);
                    }
                }
            }
        }

        $this->resource->setAttribute('prop_activity', $prop_activity);
    }

    /**
     * Process a single activity group (e.g., adm_start, adm_end).
     *
     * @param  array  $group
     * @return void
     */
    private function processActivityGroup(array &$group): void
    {
        foreach ($group as &$action) {
            if (is_array($action) && isset($action['at'])) {
                if ($this->isValidDateString($action['at'])) {
                    $this->convertUtcToClientTimezone($action['at']);
                }
            }
        }
    }

    /**
     * Convert Carbon instance to client timezone.
     *
     * @param  mixed  $date
     * @return string|mixed
     */
    private function dateAsCarbon($date)
    {
        if ($date instanceof Carbon) {
            return $date->setTimezone($this->getClientTimezone())
                ->format('Y-m-d H:i:s');
        }

        return $date;
    }

    /**
     * Normalize datetime in props field.
     *
     * Example: props->setting->last_login
     *
     * @param  string  $cast
     * @return void
     */
    private function dateAsProp(string $cast): void
    {
        $cast = Str::replace('props->', '', $cast);
        $pathParts = explode('->', $cast);
        $part = $pathParts[0];

        // Get raw attribute to avoid triggering accessors
        $attributes = $this->resource->getAttributes();

        if (!isset($attributes[$part])) {
            return;
        }

        $source = $attributes[$part];

        // If it's a JSON string, decode it
        if (is_string($source)) {
            $source = json_decode($source, true);
        }

        if (!is_array($source) && !is_object($source)) {
            return;
        }

        \array_shift($pathParts);
        $this->accessDataRecursive($source, $pathParts);
        $this->resource->setAttribute($part, $source);
    }

    /**
     * Convert datetime string from UTC to client timezone.
     *
     * @param  mixed  $date
     * @return void
     */
    private function convertUtcToClientTimezone(mixed &$date): void
    {
        try {
            // Parse as UTC (database timezone)
            $carbon = Carbon::createFromFormat('Y-m-d H:i:s', $date, 'UTC');

            // Convert to client timezone
            $date = $carbon->setTimezone($this->getClientTimezone())
                ->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            // If parsing fails, leave as-is
            // Optionally log the error
        }
    }

    /**
     * Validate if the value is a datetime string.
     *
     * @param  mixed  $date
     * @return bool
     */
    private function isValidDateString($date): bool
    {
        return isset($date) &&
               is_string($date) &&
               preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $date);
    }

    /**
     * Legacy method name (kept for backward compatibility).
     *
     * @deprecated Use isValidDateString instead
     * @param  mixed  $date
     * @return bool
     */
    private function isValidateDate($date): bool
    {
        return $this->isValidDateString($date);
    }

    /**
     * Legacy method name (kept for backward compatibility).
     *
     * @deprecated Use isValidDateString instead
     * @param  mixed  $date
     * @return bool
     */
    private function isPregMatch(mixed $date): bool
    {
        return is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $date);
    }

    /**
     * Legacy method name (kept for backward compatibility).
     *
     * @deprecated Use normalizeActivity instead
     * @return void
     */
    private function remaskingActivity(): void
    {
        $this->normalizeActivity();
    }

    /**
     * Legacy method name (kept for backward compatibility).
     *
     * @deprecated Use convertUtcToClientTimezone instead
     * @param  mixed  $date
     * @return void
     */
    private function generateDateFromFormat(mixed &$date): void
    {
        $this->convertUtcToClientTimezone($date);
    }
}
