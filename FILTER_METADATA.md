# Filter Metadata Feature

This document describes the new filter metadata feature added to the Laravel Support package.

## Overview

The filter metadata feature automatically generates filter information for API responses when dealing with collection or paginated data. This provides frontend applications with the necessary information to build dynamic search and filter interfaces.

## Features

- **Automatic Filter Generation**: Based on model `getCasts()` configuration
- **Operator Support**: Different operators based on field types
- **Custom Options**: Support for custom options via `getOptions()` method
- **Multi-Format Support**: Collection and Paginated responses

## Response Structure

### For Paginated Data

```json
{
  "data": {
    "data": [...],
    "current_page": 1,
    "per_page": 15,
    "total": 100,
    "filter": {
      "search_value": {
        "name": "search_value",
        "label": "Search",
        "type": "text",
        "options": [],
        "operators": [
          {"value": "like", "label": "Contains"}
        ]
      },
      "name": {
        "name": "name",
        "label": "Name",
        "type": "text",
        "options": [],
        "operators": [
          {"value": "like", "label": "Contains"},
          {"value": "=", "label": "Equal"},
          {"value": "!=", "label": "Not Equal"},
          {"value": "in", "label": "In"},
          {"value": "not_in", "label": "Not In"}
        ]
      },
      "age": {
        "name": "age",
        "label": "Age",
        "type": "number",
        "options": [],
        "operators": [
          {"value": "=", "label": "Equal"},
          {"value": "!=", "label": "Not Equal"},
          {"value": ">", "label": "Greater Than"},
          {"value": "<", "label": "Less Than"},
          {"value": ">=", "label": "Greater Than or Equal"},
          {"value": "<=", "label": "Less Than or Equal"},
          {"value": "between", "label": "Between"},
          {"value": "not_between", "label": "Not Between"},
          {"value": "in", "label": "In"},
          {"value": "not_in", "label": "Not In"}
        ]
      },
      "birth_date": {
        "name": "birth_date",
        "label": "Birth Date",
        "type": "date",
        "options": [],
        "operators": [
          {"value": "=", "label": "Equal"},
          {"value": "!=", "label": "Not Equal"},
          {"value": ">", "label": "After"},
          {"value": "<", "label": "Before"},
          {"value": ">=", "label": "On or After"},
          {"value": "<=", "label": "On or Before"},
          {"value": "between", "label": "Between"},
          {"value": "not_between", "label": "Not Between"},
          {"value": "in", "label": "In"},
          {"value": "not_in", "label": "Not In"}
        ]
      }
    }
  },
  "meta": {
    "code": 200,
    "success": true,
    "messages": []
  }
}
```

### For Collection Data

```json
{
  "data": {
    "data": [...],
    "filter": {
      // Same structure as paginated
    }
  },
  "meta": {
    "code": 200,
    "success": true,
    "messages": []
  }
}
```

## Using Filters in Queries

The filter metadata describes how to construct search queries. There are three supported formats:

### Format 1: Simple Search (uses default operator)

```php
// GET /api/patients?search_name=John
// Uses default operator based on type (LIKE for strings, = for numbers, between for dates)
```

### Format 2: Operator in Brackets

```php
// GET /api/patients?search_age[>]=18
// Uses > operator for age field
```

### Format 3: Separate Operator Parameter (Recommended)

```php
// GET /api/patients?search_name=John&search_name_operator=like
// Explicitly specifies the operator as a separate parameter
```

### Advanced Examples

**String search with exact match:**
```php
GET /api/patients?search_name=John Doe&search_name_operator==
```

**Numeric range:**
```php
GET /api/patients?search_age=18,65&search_age_operator=between
```

**Date filtering:**
```php
GET /api/patients?search_birth_date=1990-01-01&search_birth_date_operator=>=
```

**Multiple values (IN operator):**
```php
GET /api/patients?search_status=active,pending&search_status_operator=in
```

## Supported Operators by Type

### String/Text Fields

- `like` - Contains (default)
- `=` - Equal
- `!=` - Not Equal
- `in` - In (comma-separated values)
- `not_in` - Not In

### Numeric Fields (integer, float, double)

- `=` - Equal (default)
- `!=` - Not Equal
- `>` - Greater Than
- `<` - Less Than
- `>=` - Greater Than or Equal
- `<=` - Less Than or Equal
- `between` - Between (requires two values: value1,value2)
- `not_between` - Not Between
- `in` - In (comma-separated values)
- `not_in` - Not In

### Date Fields (date, datetime, immutable_date, immutable_datetime)

- `between` - Between (default)
- `=` - Equal
- `!=` - Not Equal
- `>` - After
- `<` - Before
- `>=` - On or After
- `<=` - On or Before
- `not_between` - Not Between
- `in` - In
- `not_in` - Not In

### Boolean Fields

- `=` - Equal

### Array/JSON Fields

- `contains` - Contains (default)
- `not_contains` - Not Contains

## Custom Options

You can provide custom options for filter fields by implementing the `getOptions()` method in your model:

### Method 1: Field-Specific Method

```php
class Patient extends Model
{
    use HasConfigDatabase;

    protected $casts = [
        'status' => 'string',
        'gender' => 'string',
    ];

    public function getOptionsStatus(): array
    {
        return [
            ['value' => 'active', 'label' => 'Active'],
            ['value' => 'inactive', 'label' => 'Inactive'],
            ['value' => 'pending', 'label' => 'Pending'],
        ];
    }

    public function getOptionsGender(): array
    {
        return [
            ['value' => 'M', 'label' => 'Male'],
            ['value' => 'F', 'label' => 'Female'],
        ];
    }
}
```

### Method 2: Generic getOptions Method

```php
class Patient extends Model
{
    use HasConfigDatabase;

    protected $casts = [
        'status' => 'string',
    ];

    public function getOptions(string $field): array
    {
        return match($field) {
            'status' => [
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'inactive', 'label' => 'Inactive'],
            ],
            default => []
        };
    }
}
```

## Backend Implementation

### HasConfigDatabase (Database Queries)

The `scopeWithParameters` method in `HasConfigDatabase` trait now supports all operators:

```php
// In your controller
$patients = Patient::query()
    ->withParameters() // Automatically reads search_* parameters from request
    ->paginate();

return Response::response($patients);
```

### HasElasticSearch (Elasticsearch Queries)

The `scopeWithElasticSearch` method in `HasElasticSearch` trait supports all operators:

```php
// In your controller
$patients = Patient::query()
    ->withElasticSearch() // Uses Elasticsearch for search
    ->paginate();

return Response::response($patients);
```

## Filter Metadata Generation

The filter metadata is automatically generated based on:

1. **Model Casts**: Determines field types and available operators
2. **Field Names**: Converted to human-readable labels (snake_case → Title Case)
3. **Options**: Retrieved from model's `getOptions()` method if available
4. **Excluded Fields**: `id`, `created_at`, `updated_at`, `deleted_at`, `props` are excluded

## Frontend Integration Example

```javascript
// Vue.js example
async function loadPatients() {
  const response = await axios.get('/api/patients', {
    params: {
      search_name: 'John',
      search_name_operator: 'like',
      search_age: 18,
      search_age_operator: '>=',
      search_status: 'active',
      search_status_operator: '='
    }
  });

  // Use filter metadata to build dynamic form
  const filters = response.data.data.filter;

  // filters.name.operators contains all available operators for the name field
  // filters.status.options contains all available options for the status field
}
```

## Complete Example

### Model Setup

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Hanafalah\LaravelSupport\Concerns\Support\HasConfigDatabase;

class Patient extends Model
{
    use HasConfigDatabase;

    protected $fillable = [
        'name',
        'email',
        'age',
        'gender',
        'birth_date',
        'status',
    ];

    protected $casts = [
        'name' => 'string',
        'email' => 'string',
        'age' => 'integer',
        'gender' => 'string',
        'birth_date' => 'date',
        'status' => 'string',
        'is_active' => 'boolean',
    ];

    /**
     * Provide options for gender field
     */
    public function getOptionsGender(): array
    {
        return [
            ['value' => 'M', 'label' => 'Male'],
            ['value' => 'F', 'label' => 'Female'],
            ['value' => 'O', 'label' => 'Other'],
        ];
    }

    /**
     * Provide options for status field
     */
    public function getOptionsStatus(): array
    {
        return [
            ['value' => 'active', 'label' => 'Active'],
            ['value' => 'inactive', 'label' => 'Inactive'],
            ['value' => 'pending', 'label' => 'Pending'],
            ['value' => 'archived', 'label' => 'Archived'],
        ];
    }
}
```

### Controller

```php
<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use Hanafalah\LaravelSupport\Facades\Response;

class PatientController extends Controller
{
    /**
     * Display a listing of patients with automatic filter metadata
     */
    public function index()
    {
        $patients = Patient::query()
            ->withParameters() // Automatically handles search_* parameters
            ->paginate(request('per-page', 15));

        // Response will automatically include filter metadata
        return Response::response($patients);
    }

    /**
     * Display a listing using Elasticsearch
     */
    public function indexWithElastic()
    {
        $patients = Patient::query()
            ->withElasticSearch() // Uses Elasticsearch for search
            ->paginate(request('per-page', 15));

        return Response::response($patients);
    }

    /**
     * Display a collection (non-paginated)
     */
    public function all()
    {
        $patients = Patient::query()
            ->withParameters()
            ->get();

        // Response will include filter metadata outside the data array
        return Response::response($patients);
    }
}
```

### API Requests Examples

**1. Simple search (uses default LIKE operator):**
```http
GET /api/patients?search_name=John
```

**2. Search with specific operator:**
```http
GET /api/patients?search_name=John&search_name_operator=like
```

**3. Numeric comparison:**
```http
GET /api/patients?search_age=18&search_age_operator=>
```

**4. Date range:**
```http
GET /api/patients?search_birth_date=1990-01-01,2000-12-31&search_birth_date_operator=between
```

**5. Multiple filters:**
```http
GET /api/patients?search_name=John&search_name_operator=like&search_age=18&search_age_operator=>=&search_status=active&search_status_operator==
```

**6. Using IN operator:**
```http
GET /api/patients?search_status=active,pending&search_status_operator=in
```

### Response Example

```json
{
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "age": 25,
        "gender": "M",
        "birth_date": "1999-01-15",
        "status": "active",
        "is_active": true
      }
    ],
    "first_page_url": "http://example.com/api/patients?page=1",
    "from": 1,
    "last_page": 10,
    "last_page_url": "http://example.com/api/patients?page=10",
    "next_page_url": "http://example.com/api/patients?page=2",
    "path": "http://example.com/api/patients",
    "per_page": 15,
    "prev_page_url": null,
    "to": 15,
    "total": 150,
    "filter": {
      "search_value": {
        "name": "search_value",
        "label": "Search",
        "type": "text",
        "options": [],
        "operators": [
          {"value": "like", "label": "Contains"}
        ]
      },
      "name": {
        "name": "name",
        "label": "Name",
        "type": "text",
        "options": [],
        "operators": [
          {"value": "like", "label": "Contains"},
          {"value": "=", "label": "Equal"},
          {"value": "!=", "label": "Not Equal"},
          {"value": "in", "label": "In"},
          {"value": "not_in", "label": "Not In"}
        ]
      },
      "email": {
        "name": "email",
        "label": "Email",
        "type": "text",
        "options": [],
        "operators": [
          {"value": "like", "label": "Contains"},
          {"value": "=", "label": "Equal"},
          {"value": "!=", "label": "Not Equal"},
          {"value": "in", "label": "In"},
          {"value": "not_in", "label": "Not In"}
        ]
      },
      "age": {
        "name": "age",
        "label": "Age",
        "type": "number",
        "options": [],
        "operators": [
          {"value": "=", "label": "Equal"},
          {"value": "!=", "label": "Not Equal"},
          {"value": ">", "label": "Greater Than"},
          {"value": "<", "label": "Less Than"},
          {"value": ">=", "label": "Greater Than or Equal"},
          {"value": "<=", "label": "Less Than or Equal"},
          {"value": "between", "label": "Between"},
          {"value": "not_between", "label": "Not Between"},
          {"value": "in", "label": "In"},
          {"value": "not_in", "label": "Not In"}
        ]
      },
      "gender": {
        "name": "gender",
        "label": "Gender",
        "type": "text",
        "options": [
          {"value": "M", "label": "Male"},
          {"value": "F", "label": "Female"},
          {"value": "O", "label": "Other"}
        ],
        "operators": [
          {"value": "like", "label": "Contains"},
          {"value": "=", "label": "Equal"},
          {"value": "!=", "label": "Not Equal"},
          {"value": "in", "label": "In"},
          {"value": "not_in", "label": "Not In"}
        ]
      },
      "birth_date": {
        "name": "birth_date",
        "label": "Birth Date",
        "type": "date",
        "options": [],
        "operators": [
          {"value": "=", "label": "Equal"},
          {"value": "!=", "label": "Not Equal"},
          {"value": ">", "label": "After"},
          {"value": "<", "label": "Before"},
          {"value": ">=", "label": "On or After"},
          {"value": "<=", "label": "On or Before"},
          {"value": "between", "label": "Between"},
          {"value": "not_between", "label": "Not Between"},
          {"value": "in", "label": "In"},
          {"value": "not_in", "label": "Not In"}
        ]
      },
      "status": {
        "name": "status",
        "label": "Status",
        "type": "text",
        "options": [
          {"value": "active", "label": "Active"},
          {"value": "inactive", "label": "Inactive"},
          {"value": "pending", "label": "Pending"},
          {"value": "archived", "label": "Archived"}
        ],
        "operators": [
          {"value": "like", "label": "Contains"},
          {"value": "=", "label": "Equal"},
          {"value": "!=", "label": "Not Equal"},
          {"value": "in", "label": "In"},
          {"value": "not_in", "label": "Not In"}
        ]
      },
      "is_active": {
        "name": "is_active",
        "label": "Is Active",
        "type": "boolean",
        "options": [],
        "operators": [
          {"value": "=", "label": "Equal"}
        ]
      }
    }
  },
  "meta": {
    "code": 200,
    "success": true,
    "messages": []
  },
  "acl": null
}
```

## Technical Details

### New Files

1. **HasFilterMetadata.php** - Trait for generating filter metadata
2. **Updated HasResponse.php** - Integrated filter metadata rendering
3. **Updated HasConfigDatabase.php** - Enhanced operator support
4. **Updated HasElasticSearch.php** - Enhanced operator support

### Key Methods

- `generateFilterMetadata(Model $model)` - Generates filter configuration
- `parseSearchKey(string $key)` - Parses search parameters with operators
- `applyStringOperator()` - Applies string operators to database queries
- `applyNumericOperator()` - Applies numeric operators to database queries
- `applyDateOperator()` - Applies date operators to database queries
- `buildElasticStringQuery()` - Builds Elasticsearch queries for strings
- `buildElasticNumericQuery()` - Builds Elasticsearch queries for numbers
- `buildElasticDateQuery()` - Builds Elasticsearch queries for dates

## Best Practices

1. **Use Separate Operator Parameters**: More explicit and easier to parse
   ```php
   ?search_name=John&search_name_operator=like
   ```

2. **Provide Custom Labels**: Override `generateFieldLabel()` if needed

3. **Define Options**: Provide options for enum-like fields

4. **Test with Different Operators**: Ensure your API handles all operators correctly

5. **Document Your API**: Include filter metadata structure in API documentation

## Migration Guide

No migration required. The feature is backward compatible:

- Existing queries without operators will use default operators
- Filter metadata is only added to collection/paginated responses
- Single model responses are unaffected

## Troubleshooting

### Filter metadata not appearing

1. Ensure you're using `Response::response($result)` for controller responses
2. Check that `$result` is a Collection or LengthAwarePaginator
3. Verify the collection is not empty (metadata is extracted from first model)

### Operators not working

1. Verify the operator is in the supported list for the field type
2. Check the parameter format: `search_{field}_operator`
3. Ensure `scopeWithParameters()` or `scopeWithElasticSearch()` is called

### Custom options not showing

1. Implement `getOptions{FieldName}()` or `getOptions($field)` in your model
2. Ensure the method returns an array
3. Check method naming: camelCase for field name (e.g., `getOptionsStatusType()` for `status_type`)
