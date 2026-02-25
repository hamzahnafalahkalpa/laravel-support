<?php

namespace Hanafalah\LaravelSupport\Examples;

use Illuminate\Database\Eloquent\Model;
use Hanafalah\LaravelSupport\Concerns\Support\HasFilterMetadata;
use Hanafalah\LaravelSupport\Facades\Response;

/**
 * Example: Using Source Type in Filter Development
 *
 * This example demonstrates how to use the source type information
 * to customize filter behavior based on whether data comes from:
 * - Model (single record)
 * - Paginate (paginated results)
 * - Collection (collection of records)
 */
class FilterTypeValidationExample
{
    use HasFilterMetadata;

    /**
     * Example 1: Basic type checking
     */
    public function example1_basicTypeCheck(): void
    {
        // Get the source type
        $type = $this->getSourceType();

        // $type will be one of: 'Model', 'Paginate', 'Collection', or null

        match ($type) {
            'Model' => $this->handleSingleRecord(),
            'Paginate' => $this->handlePaginatedData(),
            'Collection' => $this->handleCollectionData(),
            default => $this->handleUnknownType(),
        };
    }

    /**
     * Example 2: Conditional filter generation
     */
    public function example2_conditionalFilters(?Model $model = null): array
    {
        if (!$model) {
            return [];
        }

        $filters = [];

        // Add search filter only for list views (Paginate or Collection)
        if (in_array($this->getSourceType(), ['Paginate', 'Collection'])) {
            $filters['search_value'] = [
                'name' => 'search_value',
                'label' => 'Search',
                'type' => 'text',
                'operators' => [
                    ['value' => 'like', 'label' => 'Contains'],
                ]
            ];
        }

        // Add pagination controls only for Paginate type
        if ($this->isSourceType('Paginate')) {
            $filters['per_page'] = [
                'name' => 'per_page',
                'label' => 'Items Per Page',
                'type' => 'number',
                'options' => [10, 15, 25, 50, 100],
                'operators' => [
                    ['value' => '=', 'label' => 'Equal'],
                ]
            ];
        }

        // Add field-specific filters
        foreach ($model->getCasts() as $field => $castType) {
            if (in_array($field, ['id', 'created_at', 'updated_at', 'deleted_at'])) {
                continue;
            }

            $filters[$field] = [
                'name' => $field,
                'label' => ucwords(str_replace('_', ' ', $field)),
                'type' => $this->mapCastToFilterType($castType),
                'operators' => $this->getOperatorsForType($castType),
            ];
        }

        return $filters;
    }

    /**
     * Example 3: Type-specific filter operators
     */
    protected function getOperatorsForType(string $castType): array
    {
        $sourceType = $this->getSourceType();

        // For single models, limit operators to equality only
        if ($sourceType === 'Model') {
            return [
                ['value' => '=', 'label' => 'Equal'],
            ];
        }

        // For list views, provide full operator set
        return match ($castType) {
            'string', 'text' => [
                ['value' => 'like', 'label' => 'Contains'],
                ['value' => '=', 'label' => 'Equal'],
                ['value' => '!=', 'label' => 'Not Equal'],
                ['value' => 'in', 'label' => 'In'],
                ['value' => 'not_in', 'label' => 'Not In'],
            ],
            'integer', 'int', 'float', 'double' => [
                ['value' => '=', 'label' => 'Equal'],
                ['value' => '!=', 'label' => 'Not Equal'],
                ['value' => '>', 'label' => 'Greater Than'],
                ['value' => '<', 'label' => 'Less Than'],
                ['value' => 'between', 'label' => 'Between'],
            ],
            'date', 'datetime' => [
                ['value' => '=', 'label' => 'On'],
                ['value' => '>', 'label' => 'After'],
                ['value' => '<', 'label' => 'Before'],
                ['value' => 'between', 'label' => 'Between'],
            ],
            'boolean' => [
                ['value' => '=', 'label' => 'Equal'],
            ],
            default => [
                ['value' => '=', 'label' => 'Equal'],
            ],
        };
    }

    /**
     * Example 4: Validation before processing
     */
    public function example4_validateBeforeProcess(array $filters): void
    {
        // Ensure we're working with the right type
        if (!$this->isSourceType('Paginate')) {
            throw new \InvalidArgumentException(
                'This filter processor requires paginated data. ' .
                'Current type: ' . ($this->getSourceType() ?? 'unknown')
            );
        }

        // Process filters...
        foreach ($filters as $field => $value) {
            $this->applyFilter($field, $value);
        }
    }

    /**
     * Example 5: Accessing type via Response facade directly
     */
    public function example5_directAccess(): void
    {
        // You can also access the type via Response facade directly
        $type = Response::getSourceType();

        if ($type === null) {
            // No type information available
            // Either data wasn't transformed via retransform()
            // or it was returned as raw array
            echo "Type information not available\n";
            return;
        }

        // Use the type information
        echo "Data source type: {$type}\n";

        // Also get the model instance if needed
        $model = Response::getSourceModel();
        if ($model instanceof Model) {
            echo "Model class: " . get_class($model) . "\n";
        }
    }

    /**
     * Example 6: Custom filter metadata generator
     */
    public function example6_customGenerator(?Model $model = null): array
    {
        if (!$model) {
            return [];
        }

        $metadata = [
            'source_type' => $this->getSourceType(),
            'model_class' => get_class($model),
            'filters' => [],
            'capabilities' => $this->getCapabilities(),
        ];

        // Add filters based on type
        $metadata['filters'] = $this->generateFilterMetadata($model);

        return $metadata;
    }

    /**
     * Get capabilities based on source type
     */
    protected function getCapabilities(): array
    {
        return match ($this->getSourceType()) {
            'Model' => [
                'can_search' => false,
                'can_paginate' => false,
                'can_sort' => false,
                'can_filter' => false,
            ],
            'Paginate' => [
                'can_search' => true,
                'can_paginate' => true,
                'can_sort' => true,
                'can_filter' => true,
            ],
            'Collection' => [
                'can_search' => true,
                'can_paginate' => false,
                'can_sort' => true,
                'can_filter' => true,
            ],
            default => [
                'can_search' => false,
                'can_paginate' => false,
                'can_sort' => false,
                'can_filter' => false,
            ],
        };
    }

    // Handler methods for example 1
    protected function handleSingleRecord(): void
    {
        echo "Handling single record (Model)\n";
        // Don't show list-specific filters
        // Maybe show related record navigation
    }

    protected function handlePaginatedData(): void
    {
        echo "Handling paginated data\n";
        // Show search, filters, pagination controls
    }

    protected function handleCollectionData(): void
    {
        echo "Handling collection data\n";
        // Show search and filters, but not pagination
    }

    protected function handleUnknownType(): void
    {
        echo "Type information not available\n";
        // Fallback to default behavior
    }

    protected function applyFilter(string $field, mixed $value): void
    {
        // Implementation...
        echo "Applying filter: {$field} = {$value}\n";
    }
}

/**
 * Usage in Controller:
 *
 * class PatientController extends Controller
 * {
 *     use HasResponse;
 *
 *     public function index()
 *     {
 *         // This will set type to 'Paginate'
 *         $patients = Patient::paginate(15);
 *
 *         return $this->transforming(
 *             resource: PatientResource::class,
 *             callback: fn() => $patients
 *         );
 *
 *         // After this, Response::getSourceType() returns 'Paginate'
 *         // Filter metadata will be generated with pagination-specific filters
 *     }
 *
 *     public function show($id)
 *     {
 *         // This will set type to 'Model'
 *         $patient = Patient::findOrFail($id);
 *
 *         return $this->transforming(
 *             resource: PatientResource::class,
 *             callback: fn() => $patient
 *         );
 *
 *         // After this, Response::getSourceType() returns 'Model'
 *         // Filter metadata might skip list-specific filters
 *     }
 *
 *     public function all()
 *     {
 *         // This will set type to 'Collection'
 *         $patients = Patient::all();
 *
 *         return $this->transforming(
 *             resource: PatientResource::class,
 *             callback: fn() => $patients
 *         );
 *
 *         // After this, Response::getSourceType() returns 'Collection'
 *     }
 * }
 */
