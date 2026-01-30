<?php

namespace Hanafalah\LaravelSupport\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Hanafalah\LaravelSupport\Concerns\DatabaseConfiguration\HasModelConfiguration;
use Hanafalah\LaravelSupport\Concerns\Resources\HasDateNormalize;
use Hanafalah\LaravelSupport\Concerns\Support\HasArray;

class ApiResource extends JsonResource
{
    use HasArray, HasModelConfiguration, HasDateNormalize;

    /**
     * Whether to use legacy date normalization.
     *
     * Set this to false in your model's resource if you're using the
     * TimezonedDateTime cast, as it handles timezone conversion automatically.
     *
     * @var bool
     */
    protected bool $useDateNormalization = true;

    public function __construct($resource)
    {
        $this->resource = $resource;

        // Only normalize if not using TimezonedDateTime cast
        if ($this->useDateNormalization && $this->shouldNormalizeDates()) {
            try {
                $this->normalize();
            } catch (\Throwable $e) {
                // Log error but don't break the response
                // The dates will just not be normalized
                if (config('app.debug')) {
                    \Log::warning('Date normalization failed: ' . $e->getMessage(), [
                        'resource' => get_class($this),
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }
        }
    }

    /**
     * Determine if date normalization should be applied.
     *
     * @return bool
     */
    protected function shouldNormalizeDates(): bool
    {
        // Skip normalization if model uses TimezonedDateTime cast
        if ($this->resource instanceof \Illuminate\Database\Eloquent\Model) {
            try {
                $casts = $this->resource->getCasts();

                foreach ($casts as $key => $cast) {
                    // Check if any cast uses TimezonedDateTime
                    if ($cast === \Hanafalah\LaravelSupport\Casts\TimezonedDateTime::class ||
                        (class_exists($cast) && is_subclass_of($cast, \Hanafalah\LaravelSupport\Casts\TimezonedDateTime::class))) {
                        return false;
                    }
                }
            } catch (\Throwable $e) {
                // If we can't check casts (e.g., during boot), assume normalization is needed
                return true;
            }
        }

        return true;
    }

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray(\Illuminate\Http\Request $request): array
    {
        return parent::toArray($request);
    }

    public function callCustomMethod()
    {
        return ['Model'];
    }

    public function resolveNow($resource)
    {
        // return $resource->resolve();
        return json_decode(json_encode($resource), true);
    }

    public function getPropsData()
    {
        $fillable   = $this->getFillable();
        $attributes = $this->getAttributes();
        if ($this->usesTimestamps()) $fillable = $this->mergeArray($fillable, ['created_at', 'updated_at']);
        $fillable = $this->mergeArray($fillable, ['deleted_at']);
        $diff = array_diff_key($attributes, array_flip($fillable));
        return  $diff == [] ? null : $diff;
    }
}

