# Filter Type Validation Guide

This guide explains how to use the source type information in filter development to validate and customize behavior based on whether the data originates from a Model, Paginate, or Collection.

## Overview

When data is transformed from Laravel objects (Model, LengthAwarePaginator, Collection) to arrays for API responses, the original type information is preserved and can be accessed during filter metadata generation.

## Available Source Types

- `'Model'` - Single model instance
- `'Paginate'` - Paginated results (LengthAwarePaginator)
- `'Collection'` - Collection of models (Eloquent Collection or Support Collection)

## How It Works

### 1. Type is Captured Before Transformation

In `HasResponse::retransform()` (line 472-507), before data is transformed to array:

```php
// The type and model are stored BEFORE transformation
Response::setSourceModel($model);  // Stores the first model instance
Response::setSourceType($type);    // Stores 'Model', 'Paginate', or 'Collection'
```

### 2. Type is Available in Filter Generation

When `generateFilterMetadata()` is called, you can access the type:

```php
use Hanafalah\LaravelSupport\Facades\Response;

// Get the source type
$sourceType = Response::getSourceType();
// Returns: 'Model', 'Paginate', 'Collection', or null

// Check if specific type
if (Response::getSourceType() === 'Paginate') {
    // This data came from a paginated query
    // Add pagination-specific filters
}
```

### 3. Helper Methods in HasFilterMetadata

The `HasFilterMetadata` trait provides convenient helper methods:

```php
// Get the source type
$type = $this->getSourceType();

// Check if source is of specific type
if ($this->isSourceType('Paginate')) {
    // Handle paginated data differently
}

if ($this->isSourceType('Model')) {
    // Single model - maybe don't show certain filters
}

if ($this->isSourceType('Collection')) {
    // Collection - show appropriate filters
}
```

## Usage Examples

### Example 1: Different Filters Based on Type

```php
protected function generateFilterMetadata(?Model $model = null): array
{
    if (!$model) {
        return [];
    }

    $filters = [];
    $sourceType = $this->getSourceType();

    // Add universal search only for list views
    if (in_array($sourceType, ['Paginate', 'Collection'])) {
        $filters['search_value'] = [
            'name' => 'search_value',
            'label' => 'Search',
            'type' => 'text',
            'operators' => [
                ['value' => 'like', 'label' => 'Contains'],
            ]
        ];
    }

    // Add date range filters only for paginated results
    if ($this->isSourceType('Paginate')) {
        $filters['date_range'] = [
            'name' => 'date_range',
            'label' => 'Date Range',
            'type' => 'date',
            'operators' => [
                ['value' => 'between', 'label' => 'Between'],
            ]
        ];
    }

    // Generate field-specific filters
    foreach ($model->getCasts() as $field => $castType) {
        $filters[$field] = $this->generateFieldFilter($field, $castType, $sourceType);
    }

    return $filters;
}

protected function generateFieldFilter(string $field, string $castType, ?string $sourceType): array
{
    $config = [
        'name' => $field,
        'label' => $this->generateFieldLabel($field),
        'type' => $this->mapCastToFilterType($castType),
        'options' => $this->getFieldOptions($model, $field),
    ];

    // Customize operators based on source type
    if ($sourceType === 'Model') {
        // Single model - limit operators
        $config['operators'] = [
            ['value' => '=', 'label' => 'Equal'],
        ];
    } else {
        // List views - full operator set
        $config['operators'] = $this->getOperatorsByType($castType);
    }

    return $config;
}
```

### Example 2: Validation in Custom Filter Class

```php
namespace App\Filters;

use Hanafalah\LaravelSupport\Concerns\Support\HasFilterMetadata;
use Hanafalah\LaravelSupport\Facades\Response;

class CustomFilter
{
    use HasFilterMetadata;

    public function apply($query, array $filters)
    {
        // Validate that this is being used on appropriate data type
        if (!$this->isSourceType('Paginate')) {
            throw new \Exception('This filter can only be applied to paginated results');
        }

        // Apply filters...
        foreach ($filters as $field => $value) {
            $this->applyFilter($query, $field, $value);
        }

        return $query;
    }

    protected function applyFilter($query, string $field, $value)
    {
        $sourceType = $this->getSourceType();

        // Different filter logic based on source type
        match ($sourceType) {
            'Paginate' => $this->applyPaginatedFilter($query, $field, $value),
            'Collection' => $this->applyCollectionFilter($query, $field, $value),
            'Model' => $this->applyModelFilter($query, $field, $value),
            default => null,
        };
    }
}
```

### Example 3: Controller Usage

```php
namespace App\Http\Controllers;

use App\Models\Patient;
use Hanafalah\LaravelSupport\Facades\Response as ResponseFacade;

class PatientController extends Controller
{
    public function index()
    {
        // Paginated data - type will be 'Paginate'
        $patients = Patient::paginate(15);

        return $this->transforming(
            resource: PatientResource::class,
            callback: fn() => $patients
        );
        // After transformation, ResponseFacade::getSourceType() will return 'Paginate'
        // Filter metadata will be generated with pagination-specific filters
    }

    public function list()
    {
        // Collection data - type will be 'Collection'
        $patients = Patient::all();

        return $this->transforming(
            resource: PatientResource::class,
            callback: fn() => $patients
        );
        // After transformation, ResponseFacade::getSourceType() will return 'Collection'
    }

    public function show($id)
    {
        // Single model - type will be 'Model'
        $patient = Patient::findOrFail($id);

        return $this->transforming(
            resource: PatientResource::class,
            callback: fn() => $patient
        );
        // After transformation, ResponseFacade::getSourceType() will return 'Model'
        // Filter metadata generation might skip list-specific filters
    }
}
```

## Important Notes

### Octane Compatibility

The source type is stored as a static property and is automatically flushed between requests by the `flushSourceModel()` method to prevent state leakage in Laravel Octane.

```php
// In Response.php
public static function flushSourceModel(): void
{
    static::$__source_model = null;
    static::$__source_type = null;  // Type is flushed too
}
```

### When Type is Available

The source type is only available AFTER `retransform()` has been called and BEFORE the request ends (when Octane flushes state).

```php
// Type is set here
$result = $this->retransform($data, fn($item) => new MyResource($item));

// Type is available here
$type = Response::getSourceType(); // 'Paginate', 'Collection', or 'Model'

// Type is still available in filter generation
$this->renderFilterMetadata(); // Can use getSourceType()

// Type is flushed after request completes (Octane)
```

### Fallback Behavior

If type information is not available (old code, direct array responses), `getSourceType()` returns `null`:

```php
$sourceType = $this->getSourceType();

if ($sourceType === null) {
    // Type not available - use fallback logic
    // Maybe analyze the result structure instead
}
```

## API

### Response Facade Methods

```php
use Hanafalah\LaravelSupport\Facades\Response;

// Set type (called automatically by retransform)
Response::setSourceType('Paginate');

// Get type
$type = Response::getSourceType(); // 'Model', 'Paginate', 'Collection', or null

// Flush (called automatically by Octane)
Response::flushSourceModel(); // Flushes both model and type
```

### HasFilterMetadata Methods

```php
// Get source type
$type = $this->getSourceType(); // 'Model', 'Paginate', 'Collection', or null

// Check if specific type
$isPaginated = $this->isSourceType('Paginate');   // bool
$isModel = $this->isSourceType('Model');           // bool
$isCollection = $this->isSourceType('Collection'); // bool
```

## Migration Guide

### Updating Existing Filter Code

If you have existing filter generation code, you can now add type-based logic:

**Before:**
```php
protected function generateFilterMetadata(?Model $model = null): array
{
    // Same filters for all response types
    return [
        'search_value' => [...],
        'status' => [...],
    ];
}
```

**After:**
```php
protected function generateFilterMetadata(?Model $model = null): array
{
    $filters = [];

    // Only show search for list views
    if (in_array($this->getSourceType(), ['Paginate', 'Collection'])) {
        $filters['search_value'] = [...];
    }

    // Always show status filter
    $filters['status'] = [...];

    return $filters;
}
```

## Best Practices

1. **Always check for null** - Type may not be available in all contexts
2. **Use type for UI decisions** - Show/hide filters based on whether it's a list or detail view
3. **Don't rely on type for security** - Type is for UX, not authorization
4. **Use match/switch** - Clean way to handle different types
5. **Document your assumptions** - If a filter requires specific type, validate and throw helpful errors

## Troubleshooting

### Type is null when expected

**Possible causes:**
- `retransform()` was not called
- Data was returned as array directly without transformation
- Checking type too late (after Octane flush)

**Solution:**
- Ensure you're using `transforming()` or `retransform()` in your controller
- Check that Response facade's `setSourceType()` is being called

### Type persists between requests (Octane)

**Cause:** Flush is not working properly

**Solution:**
- Verify `flushSourceModel()` is registered in Octane listeners
- Check `config/octane.php` for proper flush configuration

### Wrong type detected

**Cause:** The match statement in `retransform()` may need adjustment for your use case

**Solution:**
- Review the type detection logic in `HasResponse::retransform()`
- Ensure your data structure matches one of the expected types
