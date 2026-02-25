<?php

namespace Hanafalah\LaravelSupport\Concerns\Support;

use Illuminate\Database\Eloquent\Model;

trait HasFilterMetadata
{
    /**
     * Get the source type from Response facade
     *
     * @return string|null One of: 'Model', 'Paginate', 'Collection', or null
     */
    protected function getSourceType(): ?string
    {
        return \Hanafalah\LaravelSupport\Facades\Response::getSourceType();
    }

    /**
     * Validate if source is of specific type
     *
     * @param string $type One of: 'Model', 'Paginate', 'Collection'
     * @return bool
     */
    protected function isSourceType(string $type): bool
    {
        return $this->getSourceType() === $type;
    }

    /**
     * Generate filter metadata for API responses
     *
     * @param Model|null $model
     * @return array
     */
    protected function generateFilterMetadata(?Model $model = null): array
    {
        if (!$model) {
            return [];
        }

        // You can validate the source type here if needed
        // Example: if ($this->isSourceType('Paginate')) { ... }
        // Or: $sourceType = $this->getSourceType();

        $casts = $model->getFilterCasts();
        $filters = [];

        // Add search_value for universal search
        $filters['search_value'] = [
            'name' => 'search_value',
            'label' => 'Search',
            'type' => 'text',
            'options' => [],
            'operators' => [
                ['value' => 'like', 'label' => 'Contains'],
            ]
        ];

        // Generate filters for each cast field
        foreach ($casts as $field => $castType) {
            // Skip certain fields
            if (in_array($field, ['id', 'created_at', 'updated_at', 'deleted_at', 'props'])) {
                continue;
            }

            $options = $this->getFieldOptions($model, $field);
            if (count($options) > 0){
                $operators = [];
            }else{
                $operators = $this->getOperatorsByType($castType);
            }

            $filterConfig = [
                'name' => $field,
                'label' => $this->generateFieldLabel($field),
                'type' => $this->mapCastToFilterType($castType),
                'options' => $options,
                'operators' => $operators
            ];

            $filters[$field] = $filterConfig;
        }

        return $filters;
    }

    /**
     * Generate human-readable label from field name
     *
     * @param string $field
     * @return string
     */
    protected function generateFieldLabel(string $field): string
    {
        // Convert snake_case to Title Case
        return ucwords(str_replace('_', ' ', $field));
    }

    /**
     * Map Laravel cast type to filter input type
     *
     * @param string $castType
     * @return string
     */
    protected function mapCastToFilterType(string $castType): string
    {
        return match ($castType) {
            'string', 'text' => 'text',
            'integer', 'int' => 'number',
            'float', 'double', 'decimal' => 'number',
            'boolean', 'bool' => 'boolean',
            'date', 'datetime', 'timestamp', 'immutable_date', 'immutable_datetime' => 'date',
            'array', 'json' => 'array',
            default => 'text',
        };
    }

    /**
     * Get field options if model has getOptions method
     *
     * @param Model $model
     * @param string $field
     * @return array
     */
    protected function getFieldOptions(Model $model, string $field): array
    {
        // Check if model has getOptions method for this field
        $methodName = 'getCustomFilterOptions' . ucfirst(\Illuminate\Support\Str::camel($field));

        if (method_exists($model, $methodName)) {
            $options = $model->{$methodName}();
            return is_array($options) ? $options : [];
        }

        // Check if model has generic getCustomFilterOptions method with parameter
        if (method_exists($model, 'getCustomFilterOptions')) {
            $options = $model->getCustomFilterOptions($field);
            return is_array($options) ? $options : [];
        }

        return [];
    }

    /**
     * Get available operators based on cast type
     *
     * @param string $castType
     * @return array
     */
    protected function getOperatorsByType(string $castType): array
    {
        return match ($castType) {
            'string', 'text' => [
                ['value' => 'like', 'label' => 'Contains'],
                ['value' => '=', 'label' => 'Equal'],
                ['value' => '!=', 'label' => 'Not Equal'],
                ['value' => 'in', 'label' => 'In'],
                ['value' => 'not_in', 'label' => 'Not In'],
            ],
            'integer', 'int', 'float', 'double', 'decimal' => [
                ['value' => '=', 'label' => 'Equal'],
                ['value' => '!=', 'label' => 'Not Equal'],
                ['value' => '>', 'label' => 'Greater Than'],
                ['value' => '<', 'label' => 'Less Than'],
                ['value' => '>=', 'label' => 'Greater Than or Equal'],
                ['value' => '<=', 'label' => 'Less Than or Equal'],
                ['value' => 'between', 'label' => 'Between'],
                ['value' => 'not_between', 'label' => 'Not Between'],
                ['value' => 'in', 'label' => 'In'],
                ['value' => 'not_in', 'label' => 'Not In'],
            ],
            'date', 'datetime', 'timestamp', 'immutable_date', 'immutable_datetime' => [
                ['value' => '=', 'label' => 'Equal'],
                ['value' => '!=', 'label' => 'Not Equal'],
                ['value' => '>', 'label' => 'After'],
                ['value' => '<', 'label' => 'Before'],
                ['value' => '>=', 'label' => 'On or After'],
                ['value' => '<=', 'label' => 'On or Before'],
                ['value' => 'between', 'label' => 'Between'],
                ['value' => 'not_between', 'label' => 'Not Between'],
                ['value' => 'in', 'label' => 'In'],
                ['value' => 'not_in', 'label' => 'Not In'],
            ],
            'boolean', 'bool' => [
                ['value' => '=', 'label' => 'Equal'],
            ],
            'array', 'json' => [
                ['value' => 'contains', 'label' => 'Contains'],
                ['value' => 'not_contains', 'label' => 'Not Contains'],
            ],
            default => [
                ['value' => '=', 'label' => 'Equal'],
                ['value' => '!=', 'label' => 'Not Equal'],
            ],
        };
    }

    /**
     * Extract model from result data
     *
     * @param mixed $result
     * @return Model|null
     */
    protected function extractModelFromResult(mixed $result): ?Model
    {
        // First, check if we have a stored source model from Response facade
        // This handles cases where data has already been transformed to array
        // You can also get the type using: Response::getSourceType()
        // Type will be one of: 'Model', 'Paginate', 'Collection'
        $sourceModel = \Hanafalah\LaravelSupport\Facades\Response::getSourceModel();
        if ($sourceModel instanceof Model) {
            return $sourceModel;
        }

        // Check if result is a paginator
        if ($result instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            $collection = $result->getCollection();
            return $collection->first();
        }

        // Check if result is a collection
        if ($result instanceof \Illuminate\Database\Eloquent\Collection) {
            return $result->first();
        }

        // Check if result is a support collection
        if ($result instanceof \Illuminate\Support\Collection) {
            return $result->first();
        }

        // Check if result is an array with 'data' key
        if (is_array($result) && isset($result['data'])) {
            // Handle nested data structure
            if (is_array($result['data']) && !empty($result['data'])) {
                // Try to get first element if it's an array of items
                $firstItem = is_array($result['data']) ? reset($result['data']) : $result['data'];

                // If first item has a model class, try to instantiate it
                if (is_array($firstItem) && isset($firstItem['id'])) {
                    // This is likely already transformed data, can't extract model
                    return null;
                }

                return $this->extractModelFromResult($result['data']);
            }
        }

        // Check if result is a model instance
        if ($result instanceof Model) {
            return $result;
        }

        return null;
    }
}
