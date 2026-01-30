<?php

namespace Hanafalah\LaravelSupport\Casts;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Custom cast for timezone-aware DateTime handling.
 *
 * - Database: Always stores in UTC
 * - Get (output): Converts from UTC to client timezone
 * - Set (input): Converts from client timezone to UTC
 *
 * This is Octane-safe as it doesn't use static variables and relies on
 * per-request timezone set by SetClientTimezone middleware.
 *
 * Usage in model:
 * protected $casts = [
 *     'created_at' => TimezonedDateTime::class,
 *     'scheduled_at' => TimezonedDateTime::class,
 * ];
 */
class TimezonedDateTime implements CastsAttributes
{
    /**
     * The database timezone (always UTC).
     *
     * @var string
     */
    protected string $databaseTimezone = 'UTC';

    /**
     * Cast the given value.
     *
     * Converts from UTC (database) to client timezone (output).
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array  $attributes
     * @return \Carbon\Carbon|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        // Parse the value as UTC from database
        $carbon = $this->asDateTime($value);

        // Convert to client timezone
        $clientTimezone = $this->getClientTimezone();

        return $carbon->setTimezone($clientTimezone);
    }

    /**
     * Prepare the given value for storage.
     *
     * Converts from client timezone (input) to UTC (database).
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array  $attributes
     * @return string|null
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        // Parse the value in client timezone
        $carbon = $this->asDateTime($value);

        // If the value doesn't have timezone info, assume it's in client timezone
        if (!$carbon->timezone || $carbon->timezone->getName() === $this->databaseTimezone) {
            $clientTimezone = $this->getClientTimezone();
            $carbon = Carbon::parse($value, $clientTimezone);
        }

        // Convert to UTC for storage
        return $carbon->setTimezone($this->databaseTimezone)->format('Y-m-d H:i:s');
    }

    /**
     * Get the client timezone for the current request.
     *
     * @return string
     */
    protected function getClientTimezone(): string
    {
        // Get from request context (set by middleware)
        if ($request = request()) {
            if ($timezone = $request->attributes->get('client_timezone')) {
                return $timezone;
            }
        }

        // Fallback to current timezone or config
        return date_default_timezone_get() ?: config('app.client_timezone', 'UTC');
    }

    /**
     * Parse the value as a Carbon instance.
     *
     * @param  mixed  $value
     * @return \Carbon\Carbon
     */
    protected function asDateTime(mixed $value): Carbon
    {
        // If already Carbon instance, return it
        if ($value instanceof CarbonInterface) {
            return Carbon::instance($value);
        }

        // If it's a timestamp
        if (is_numeric($value)) {
            return Carbon::createFromTimestamp($value, $this->databaseTimezone);
        }

        // Parse as datetime string in UTC
        return Carbon::parse($value, $this->databaseTimezone);
    }
}
