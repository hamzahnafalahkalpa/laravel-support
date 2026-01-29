# Elasticsearch Integration Documentation

## Overview

The Elasticsearch integration provides model-level search capabilities, allowing models to automatically route queries through Elasticsearch for improved search performance. When enabled on a model, search queries are processed by Elasticsearch first, then results are fetched from the database to preserve relationships and maintain data integrity.

## Features

- **Model-Level Control**: Enable/disable Elasticsearch per model via configuration
- **Backward Compatible**: Models without Elasticsearch config work exactly as before
- **Auto-Indexing**: Automatically sync model changes (create/update/delete) to Elasticsearch
- **Multi-Tenant Support**: Dynamic index prefixing based on tenant ID
- **Circuit Breaker**: Automatic fallback to database after consecutive failures
- **Type-Aware Queries**: Respects model cast types for intelligent query building
- **Graceful Degradation**: Falls back to database on Elasticsearch errors

## Installation

### 1. Environment Configuration

Add the following to your `.env` file:

```bash
# Enable Elasticsearch globally
ELASTICSEARCH_ENABLED=true

# Elasticsearch connection details
ELASTICSEARCH_HOSTS=10.100.14.59:9200
ELASTICSEARCH_USERNAME=elastic
ELASTICSEARCH_PASSWORD=password

# Optional: Static prefix (overridden by tenant ID if multi-tenant)
ELASTICSEARCH_PREFIX=production

# Optional: Tenant ID for multi-tenant setup
TENANT_ID=tenant-001

# Optional: SSL verification
ELASTICSEARCH_SSL_VERIFY=false
```

### 2. Configuration File

The main configuration is located at `config/elasticsearch.php`:

```php
<?php

return [
    // Enable/disable Elasticsearch globally
    'enabled' => env('ELASTICSEARCH_ENABLED', false),

    // Connection settings
    'hosts' => [env('ELASTICSEARCH_HOSTS', 'localhost:9200')],
    'username' => env('ELASTICSEARCH_USERNAME', 'elastic'),
    'password' => env('ELASTICSEARCH_PASSWORD', 'password'),

    // Dynamic index prefix (set automatically from tenant ID)
    'prefix' => env('ELASTICSEARCH_PREFIX', env('APP_ENV', 'development')),
    'separator' => '.',

    // Query settings
    'query_timeout' => 5, // seconds

    // Circuit breaker configuration
    'circuit_breaker' => [
        'enabled' => true,
        'failure_threshold' => 5,
        'cooldown_minutes' => 5,
    ],

    // Auto-indexing configuration
    'auto_index' => [
        'enabled' => true,
        'queue' => 'elasticsearch',
        'connection' => 'rabbitmq',
    ],
];
```

## Enabling Elasticsearch on Models

### Basic Configuration

Add the `$elastic_config` array to any model that extends `SupportBaseModel`:

```php
<?php

namespace YourNamespace\Models;

use Hanafalah\LaravelSupport\Models\SupportBaseModel;

class Patient extends SupportBaseModel
{
    protected array $elastic_config = [
        'enabled' => true,              // Enable Elasticsearch for this model
        'index_name' => 'patient',      // Elasticsearch index name
        'variables' => [                // Fields to index and search
            'id',
            'name',
            'medical_record',
            'first_name',
            'last_name',
            'dob',
            'nik'
        ],
        'hydrate' => false,             // false = use ES source, true = fetch from DB
    ];

    // ... rest of your model
}
```

### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enabled` | boolean | false | Enable/disable Elasticsearch for this model |
| `index_name` | string/null | table name | Elasticsearch index name. If null, uses table name |
| `variables` | array | all casts | Fields to index. If empty, uses all fields from `getCasts()` |
| `hydrate` | boolean | false | Whether to fetch full records from DB after ES query |

### Field Selection

**Option 1: Explicit Field List (Recommended)**
```php
'variables' => [
    'id',
    'name',
    'medical_record',
    'first_name',
    'last_name'
]
```

**Option 2: Auto-Detect from Casts (Default)**
```php
'variables' => []  // Uses all fields from getCasts() except props, timestamps
```

## Usage

### Basic Search (No Code Changes Required!)

Once Elasticsearch is enabled on a model, existing search queries automatically use it:

```php
// In your controller - works exactly as before
$patients = $this->__patient_schema->viewPatientPaginate();

// API request
// GET /api/patients?search_name=john&search_medical_record=MR001
```

### Query Parameters

All existing `search_*` parameters work automatically:

```php
// Search by name (LIKE behavior)
GET /api/patients?search_name=john

// Search by exact ID
GET /api/patients?search_id=123

// Multiple search criteria
GET /api/patients?search_name=john&search_dob=1990-01-01

// With pagination
GET /api/patients?search_name=john&per_page=20&page=2

// With sorting
GET /api/patients?search_name=john&order_by=name&order_type=asc
```

### Query Type Behavior

The query builder respects model cast types:

| Cast Type | Elasticsearch Query Type | Behavior |
|-----------|-------------------------|----------|
| `string`, `text` | `multi_match` with `phrase_prefix` | LIKE behavior (partial match) |
| `array`, `json` | `terms` or `term` | Array contains |
| `date`, `datetime` | `range` or `term` | Date range or exact match |
| `boolean`, `bool` | `term` | Exact boolean match |
| `integer`, `int`, `float` | `term` | Exact numeric match |

### Date Range Queries

```php
// Single date (exact match)
GET /api/visits?search_visit_date=2024-01-01

// Date range (requires custom implementation)
$parameters = [
    'search_visit_date' => [
        'from' => '2024-01-01',
        'to' => '2024-12-31'
    ]
];
```

## Multi-Tenancy

### Automatic Tenant Prefix

Index names are automatically prefixed with tenant ID:

```
Tenant ID: tenant-001
Model index: patient
Full index name: tenant-001.patient
```

### Tenant ID Detection

The system checks for tenant ID in this order:

1. `tenancy()->tenant->id` (Tenancy package)
2. `session('tenant_id')` (Session)
3. `request()->header('X-Tenant-ID')` (HTTP header)
4. `config('tenant.current_id')` (Config)
5. `env('TENANT_ID')` (Environment variable)
6. Falls back to `APP_ENV` (e.g., "development")

### Manual Prefix Override

```php
// In a service provider or middleware
config(['elasticsearch.prefix' => 'custom-prefix']);
```

## Auto-Indexing

### How It Works

The `ElasticSearchObserver` automatically indexes models on CRUD operations:

- **Created**: Indexes new record
- **Updated**: Re-indexes updated record
- **Deleted**: Removes from index

### Indexing Process

1. Model event triggers (created/updated/deleted)
2. Observer extracts searchable data
3. Job dispatched to RabbitMQ queue
4. ElasticJob processes in background
5. Data synced to Elasticsearch

### Disabling Auto-Indexing

**Globally:**
```php
// config/elasticsearch.php
'auto_index' => [
    'enabled' => false,
]
```

**Per Model:**
```php
// Override the booted() method
protected static function booted(): void
{
    parent::booted();

    // Don't register observer for this model
    // (requires custom implementation)
}
```

### Manual Indexing

```php
use WellmedGateway\Jobs\ElasticJob;

// Index a single record
dispatch(new ElasticJob([
    'type' => 'BULK',
    'datas' => [[
        'index' => 'tenant-001.patient',
        'action' => 'index',
        'data' => [[
            'id' => 1,
            'name' => 'John Doe',
            'medical_record' => 'MR001'
        ]]
    ]]
]))->onQueue('elasticsearch')->onConnection('rabbitmq');

// Delete from index
dispatch(new ElasticJob([
    'type' => 'DELETE',
    'datas' => [[
        'index' => 'tenant-001.patient',
        'id' => 1
    ]]
]))->onQueue('elasticsearch')->onConnection('rabbitmq');
```

### Bulk Indexing Existing Data

**Important:** The auto-indexing observer only works for **new** records going forward. Existing data in your database must be indexed manually.

#### Using the Artisan Command

Use the `elasticsearch:index` command to bulk index existing records:

```bash
# Basic usage - index all records
php artisan elasticsearch:index Patient

# With full model class name
php artisan elasticsearch:index "Projects\ModulePatient\Models\Patient\Patient"

# Process in smaller batches (default 100)
php artisan elasticsearch:index Patient --chunk=50

# Start from a specific ID (useful for resuming)
php artisan elasticsearch:index Patient --from=1000

# Limit total records (for testing)
php artisan elasticsearch:index Patient --limit=100

# Combined options
php artisan elasticsearch:index Patient --chunk=200 --from=5000 --limit=10000
```

#### Command Options

| Option | Default | Description |
|--------|---------|-------------|
| `--chunk` | 100 | Number of records to process per batch |
| `--from` | 0 | Start from this record ID (useful for resuming after failure) |
| `--limit` | 0 | Limit total records to index (0 = all records) |

#### Example: Index All Patients

```bash
# Make sure queue worker is running first!
php artisan queue:work rabbitmq --queue=elasticsearch

# In another terminal, run the indexing
php artisan elasticsearch:index Patient

# Output:
# Starting indexing for model: Projects\ModulePatient\Models\Patient\Patient
# Index: tenant-001.patient
#
# Total records to index: 45320
#
# Do you want to proceed with indexing? (yes/no) [yes]:
# > yes
#
# 45320/45320 [============================] 100%
#
# Indexing completed!
# Successfully queued: 45320
# Failed: 0
#
# Jobs have been dispatched to the 'elasticsearch' queue.
# Make sure your queue worker is running: php artisan queue:work rabbitmq --queue=elasticsearch
```

#### Example: Index Specific Range

```bash
# Index patients 10000 to 20000
php artisan elasticsearch:index Patient --from=10000 --limit=10000
```

#### Example: Re-index After Failure

```bash
# If indexing failed at ID 15000, resume from there
php artisan elasticsearch:index Patient --from=15000
```

#### Queue Worker

**Important:** Make sure your queue worker is running before indexing:

```bash
# Start queue worker for elasticsearch queue
php artisan queue:work rabbitmq --queue=elasticsearch

# Or with options
php artisan queue:work rabbitmq --queue=elasticsearch --tries=3 --timeout=60

# Run in background (production)
nohup php artisan queue:work rabbitmq --queue=elasticsearch > /dev/null 2>&1 &

# Or use supervisor (recommended for production)
```

#### Monitoring Progress

Check queue status:

```bash
# Check queue jobs
php artisan queue:work rabbitmq --queue=elasticsearch -vvv

# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

#### Indexing Multiple Models

```bash
# Index different models one by one
php artisan elasticsearch:index Patient
php artisan elasticsearch:index Visit
php artisan elasticsearch:index Country
```

#### Create a Bulk Indexing Script

For multiple models, create a script:

```bash
#!/bin/bash
# index_all.sh

echo "Starting bulk indexing..."

php artisan elasticsearch:index Patient --chunk=200
php artisan elasticsearch:index Visit --chunk=200
php artisan elasticsearch:index Country --chunk=100
php artisan elasticsearch:index Province --chunk=100

echo "All indexing jobs dispatched!"
echo "Monitor queue: php artisan queue:work rabbitmq --queue=elasticsearch"
```

Make it executable:
```bash
chmod +x index_all.sh
./index_all.sh
```

#### Performance Tips

1. **Adjust chunk size** based on record size:
   - Small records (Country, Province): `--chunk=500`
   - Medium records (Patient): `--chunk=100-200`
   - Large records (Visit with relations): `--chunk=50`

2. **Monitor memory usage** during indexing:
   ```bash
   watch -n 1 'ps aux | grep queue:work'
   ```

3. **Run during off-peak hours** for large datasets

4. **Use multiple queue workers** for faster indexing:
   ```bash
   # Terminal 1
   php artisan queue:work rabbitmq --queue=elasticsearch

   # Terminal 2
   php artisan queue:work rabbitmq --queue=elasticsearch

   # Terminal 3
   php artisan queue:work rabbitmq --queue=elasticsearch
   ```

#### Verify Indexing

After indexing, verify the data:

```bash
# Check Elasticsearch directly
curl -X GET "localhost:9200/tenant-001.patient/_count?pretty"

# Should show total documents indexed
{
  "count" : 45320,
  ...
}
```

## Circuit Breaker

### Purpose

Prevents cascading failures by temporarily disabling Elasticsearch after consecutive errors.

### Behavior

1. Elasticsearch query fails
2. Failure counter increments (cached per model class)
3. After 5 failures (default), circuit opens
4. All queries fall back to database for 5 minutes (default)
5. Circuit automatically resets after cooldown
6. Successful query resets failure counter

### Configuration

```php
// config/elasticsearch.php
'circuit_breaker' => [
    'enabled' => true,              // Enable circuit breaker
    'failure_threshold' => 5,       // Number of failures before opening
    'cooldown_minutes' => 5,        // Minutes before auto-reset
]
```

### Monitoring

Check logs for circuit breaker events:

```
[warning] Elasticsearch circuit breaker opened
[model] => App\Models\Patient
[failures] => 5
[cooldown_minutes] => 5
```

## Error Handling & Fallback

### Fallback Strategy

On any Elasticsearch error:

1. Error is caught and logged
2. Query falls back to standard database
3. User experience unchanged
4. Circuit breaker tracks failure

### Error Scenarios

| Scenario | Behavior |
|----------|----------|
| Elasticsearch down | Fall back to database, log warning |
| Index missing | Fall back to database, log error |
| Query syntax error | Fall back to database, log error with query |
| Timeout | Fall back to database, increment circuit breaker |
| Empty results | Return empty collection (not an error) |
| Circuit open | Skip Elasticsearch, use database directly |

### Logging

All errors are logged with context:

```php
[warning] Elasticsearch query failed, falling back to database
[error] => Connection refused
[model] => App\Models\Patient
[query] => {...}
[index] => tenant-001.patient
```

## Performance Considerations

### Query Flow

**Without Elasticsearch:**
```
Controller → Schema → Model → Database → Results
```

**With Elasticsearch:**
```
Controller → Schema → Model → Elasticsearch (get IDs) → Database (filter by IDs) → Results
```

### When to Use

**Good Use Cases:**
- Large datasets (100k+ records)
- Complex text search (names, descriptions)
- Multi-field searches
- Fuzzy matching requirements
- High search volume

**Not Recommended:**
- Small datasets (<10k records)
- Simple exact-match queries
- Low search volume
- Frequently changing schemas

### Optimization Tips

1. **Index only searchable fields**: Don't index every field
2. **Use hydrate: false**: Avoid double queries when possible
3. **Monitor query performance**: Compare ES vs DB query times
4. **Tune pagination**: Balance page size with performance
5. **Use appropriate cast types**: Helps build efficient ES queries

## Examples

### Example 1: Patient Model

```php
<?php

namespace Projects\ModulePatient\Models\Patient;

use Hanafalah\LaravelSupport\Models\SupportBaseModel;

class Patient extends SupportBaseModel
{
    protected $fillable = [
        'name', 'first_name', 'last_name', 'medical_record',
        'dob', 'nik', 'phone', 'address'
    ];

    protected function casts()
    {
        return array_merge(parent::casts(), [
            'id' => 'integer',
            'name' => 'string',
            'first_name' => 'string',
            'last_name' => 'string',
            'medical_record' => 'string',
            'dob' => 'date',
            'nik' => 'string',
            'phone' => 'string',
            'address' => 'string'
        ]);
    }

    protected array $elastic_config = [
        'enabled' => true,
        'index_name' => 'patient',
        'variables' => [
            'id',
            'name',
            'medical_record',
            'first_name',
            'last_name',
            'dob',
            'nik'
        ],
        'hydrate' => false
    ];
}
```

**Usage:**
```php
// Search by name
GET /api/patients?search_name=john

// Search by medical record
GET /api/patients?search_medical_record=MR

// Multiple criteria
GET /api/patients?search_name=john&search_dob=1990-01-01
```

### Example 2: Country Model (Read-Only)

```php
<?php

namespace App\Models;

use Hanafalah\LaravelSupport\Models\SupportBaseModel;

class Country extends SupportBaseModel
{
    protected $fillable = ['code', 'name', 'phone_code'];

    protected function casts()
    {
        return array_merge(parent::casts(), [
            'code' => 'string',
            'name' => 'string',
            'phone_code' => 'string'
        ]);
    }

    protected array $elastic_config = [
        'enabled' => true,
        'index_name' => 'country',
        'variables' => [],  // Auto-detect from casts
        'hydrate' => false
    ];
}
```

**Usage:**
```php
// Search countries
GET /api/countries?search_name=indo

// Search by code
GET /api/countries?search_code=ID
```

### Example 3: Visit Model

```php
<?php

namespace Projects\ModuleVisit\Models\Visit;

use Hanafalah\LaravelSupport\Models\SupportBaseModel;

class Visit extends SupportBaseModel
{
    protected $table = 'visit_patient';

    protected function casts()
    {
        return array_merge(parent::casts(), [
            'visit_number' => 'string',
            'patient_id' => 'integer',
            'visit_date' => 'date',
            'status' => 'string'
        ]);
    }

    protected array $elastic_config = [
        'enabled' => true,
        'index_name' => 'visit_patient',  // Must match existing index
        'variables' => [
            'id',
            'visit_number',
            'patient_id',
            'visit_date',
            'status'
        ],
        'hydrate' => false
    ];
}
```

**Usage:**
```php
// Search visits by number
GET /api/visits?search_visit_number=V2024

// Search by patient ID
GET /api/visits?search_patient_id=123

// Search by status
GET /api/visits?search_status=completed
```

## Troubleshooting

### Elasticsearch Not Being Used

**Check:**
1. Is `ELASTICSEARCH_ENABLED=true` in `.env`?
2. Is `$elastic_config['enabled'] = true` in model?
3. Is circuit breaker open? (check logs)
4. Is the index created in Elasticsearch?

**Debug:**
```php
// Check if model has ES enabled
$model = new Patient();
dd($model->isElasticSearchEnabled());

// Check index name
dd($model->getElasticIndexName());
```

### No Search Results

**Check:**
1. Are records indexed? (run manual indexing)
2. Is the index name correct?
3. Is the field name in `variables` array?
4. Check Elasticsearch logs for query errors

**Test index directly:**
```bash
curl -X GET "localhost:9200/tenant-001.patient/_search?pretty" -H 'Content-Type: application/json' -d'
{
  "query": {
    "match_all": {}
  }
}
'
```

### Circuit Breaker Keeps Opening

**Causes:**
- Elasticsearch is down or unreachable
- Network issues between app and ES
- Index doesn't exist
- Query syntax errors

**Solutions:**
1. Check Elasticsearch service status
2. Verify network connectivity
3. Create missing indexes
4. Check application logs for error details
5. Temporarily disable ES: `ELASTICSEARCH_ENABLED=false`

### Auto-Indexing Not Working

**Check:**
1. Is RabbitMQ running?
2. Is the queue worker running?
3. Is `auto_index.enabled = true`?
4. Check queue logs

**Start queue worker:**
```bash
php artisan queue:work rabbitmq --queue=elasticsearch
```

### Different Results Between ES and Database

**Possible Causes:**
1. Index is out of sync
2. Different query logic for complex searches
3. Cast type mismatch

**Solutions:**
1. Re-index all records
2. Compare ES query vs DB query in logs
3. Verify cast types match expected behavior

## Known Limitations

### Query Logic (AND/OR)

The `scopeWithElasticSearch` method accepts an `$operator` parameter ('and' or 'or') to match the signature of `scopeWithParameters`, but currently **only AND logic is implemented** in Elasticsearch queries.

All search criteria are combined using Elasticsearch's `must` clause, which means:
- `search_name=john&search_dob=1990-01-01` → Find records where name LIKE 'john' AND dob = '1990-01-01'

**OR logic** would require a different Elasticsearch query structure using `should` clauses. This can be added in a future enhancement if needed.

**Workaround:** If you need OR logic, you can:
1. Disable Elasticsearch for that specific query (falls back to database `withParameters`)
2. Build a custom Elasticsearch query
3. Request the OR logic feature enhancement

## Best Practices

### 1. Start Small
Enable on low-risk models first (Country, Province) to test functionality.

### 2. Index Only What You Search
Don't add fields to `variables` unless users search by them.

### 3. Monitor Performance
Compare query times before and after enabling Elasticsearch.

### 4. Keep Indexes in Sync
Run periodic re-indexing for critical models.

### 5. Use Appropriate Cast Types
Ensure model casts match your search requirements.

### 6. Test Fallback Behavior
Disable Elasticsearch and ensure app still works.

### 7. Monitor Circuit Breaker
Set up alerts for circuit breaker events.

### 8. Document Model Configs
Comment why specific fields are indexed.

## API Reference

### HasElasticSearch Trait Methods

```php
// Check if Elasticsearch is enabled for this model
public function isElasticSearchEnabled(): bool

// Get the full index name with prefix
public function getElasticIndexName(): string

// Get fields that should be indexed
public function getElasticSearchableFields(): array

// Build Elasticsearch query from search parameters
public function buildElasticQuery(array $parameters): array

// Execute Elasticsearch query and return IDs and total
public function executeElasticQuery(array $esQuery, int $perPage, int $page, array $sort): array

// Query builder scope for Elasticsearch filtering
// $operator: 'and' or 'or' (currently only 'and' is used in ES queries)
// $parameters: Search parameters (defaults to request search_* params)
public function scopeWithElasticSearch(Builder $query, string $operator = 'and', mixed $parameters = null): Builder

// Get data formatted for Elasticsearch indexing
public function toElasticArray(): array
```

### ElasticSearchObserver Events

```php
// Triggered on model creation
public function created(Model $model): void

// Triggered on model update
public function updated(Model $model): void

// Triggered on model deletion
public function deleted(Model $model): void
```

## Version History

### Version 1.0 (2024)
- Initial implementation
- Model-level configuration
- Auto-indexing observer
- Circuit breaker pattern
- Multi-tenant support
- Type-aware query building
- Graceful fallback

## Support

For issues or questions:
1. Check application logs: `storage/logs/laravel.log`
2. Check Elasticsearch logs
3. Check RabbitMQ queue status
4. Review this documentation
5. Contact the development team

---

**Last Updated:** 2024-01-28
**Maintainer:** Development Team
