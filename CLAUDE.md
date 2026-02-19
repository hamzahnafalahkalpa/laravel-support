# CLAUDE.md - Laravel Support

This file provides guidance to Claude Code when working with this module.

## CRITICAL: This is the Foundation Package

**`hanafalah/laravel-support` is the BASE package for ALL modules in the Wellmed ecosystem.**

Every module depends on this package:
- All `repositories/module-*` packages
- All `projects/*` applications
- All `features/ms-*` microservices

**Any breaking change here will cascade to 60+ modules and crash the entire system.**

## CRITICAL: Memory Exhaustion Issues

The `registers()` method in `BaseServiceProvider` is the most dangerous code path. It can cause memory exhaustion (536MB limit) when:

### Pattern: `registers(['*'])` - Now Optimized

```php
// In child ServiceProvider
public function register()
{
    $this->registerMainClass(MyModule::class)
        ->registers(['*']);  // NOW SAFE - only registers safe methods
}
```

**After optimization (v2.0):**
- `registers(['*'])` now only registers SAFE methods: `Config, Model, Database, Migration, Route, Namespace, Provider`
- Dangerous methods (`Schema`, `Services`) are excluded from `'*'` by default
- To use dangerous methods, explicitly call them: `->registers(['Schema'])`

**Safe register methods:**
```php
protected const SAFE_REGISTER_METHODS = [
    'Config', 'Model', 'Database', 'Migration', 'Route', 'Namespace', 'Provider'
];
```

**Dangerous methods (must be explicit):**
```php
protected const DANGEROUS_REGISTER_METHODS = [
    'Schema', 'Services'
];
```

### Safe Pattern: Explicit Registration

```php
// SAFE - only register what you need
public function register()
{
    $this->registerMainClass(MyModule::class);
    // Register singletons manually with closures (deferred)
    $this->app->singleton(MyService::class, fn() => new MyService());
}
```

### The Problem Chain

```
registers(['*'])
    └── registerSchema()
        └── Loads Schema classes
            └── Schema extends BasePackageManagement
                └── Uses HasModelConfiguration trait
                    └── Calls config('database.models')
                        └── Triggers more loading
                            └── MEMORY EXHAUSTED
```

## Architecture Overview

```
laravel-support/
├── src/
│   ├── Providers/
│   │   └── BaseServiceProvider.php    # BASE CLASS - all modules extend this
│   ├── Concerns/
│   │   ├── DatabaseConfiguration/
│   │   │   ├── HasModelConfiguration.php  # DANGEROUS - causes memory issues
│   │   │   └── HasDatabaseConfiguration.php
│   │   ├── ServiceProvider/
│   │   │   ├── HasConfiguration.php
│   │   │   ├── HasMigrationConfiguration.php
│   │   │   ├── HasProviderConfiguration.php
│   │   │   └── HasRouteConfiguration.php
│   │   ├── Support/
│   │   │   ├── HasCall.php           # Dynamic method calling
│   │   │   ├── HasArray.php          # Array utilities
│   │   │   ├── HasCache.php
│   │   │   ├── HasMicrotenant.php    # Multi-tenancy support
│   │   │   └── ... (30+ traits)
│   │   └── PackageManagement/
│   │       ├── DataManagement.php
│   │       └── HasEvent.php
│   ├── Supports/
│   │   ├── PackageManagement.php     # Base class for Schemas
│   │   ├── BasePackageManagement.php
│   │   └── PathRegistry.php
│   ├── Models/
│   │   └── BaseModel.php             # Base model for all modules
│   ├── Controllers/
│   │   └── Controller.php            # Base controller
│   └── helper.php                    # Global helper functions
```

## Key Classes

### BaseServiceProvider

The foundation class all module service providers extend.

**Key methods:**

| Method | Purpose | Risk Level |
|--------|---------|------------|
| `registerMainClass()` | Register main module class | Safe |
| `registers(['*'])` | Auto-register all | **DANGEROUS** |
| `registers(['Schema'])` | Register specific | Medium |
| `registerConfig()` | Merge config files | Safe |
| `binds()` | Register singletons | Safe |
| `appBooting()` | Booting callback | Safe |
| `appBooted()` | Booted callback | Safe |

**Safe usage:**
```php
class MyServiceProvider extends BaseServiceProvider
{
    public function register()
    {
        $this->registerMainClass(MyModule::class);
        // Don't use registers() - register manually
    }

    protected function dir(): string
    {
        return __DIR__ . '/';
    }
}
```

### HasModelConfiguration Trait

**This trait is the root cause of most memory issues.**

Located at: `src/Concerns/DatabaseConfiguration/HasModelConfiguration.php`

It provides dynamic model resolution via `__callModel()`:
```php
// Calling $this->UserModel() returns new User()
// Based on config('database.models.User')
```

**Problem:** When a class using this trait is loaded, it may call `config('database.models')` which triggers more class loading.

### PackageManagement

Base class for Schema classes. Uses multiple traits including `HasModelConfiguration`.

**Classes extending this are dangerous to auto-load:**
- Any `Schemas/*.php` class
- Any `Supports/Base*.php` class

## ProviderRegisterMethod Enum

Located at: `src/Enums/Provider/ProviderRegisterMethod.php`

Defines what `registers()` can auto-register:
- `CONFIG` - Configuration files
- `MODEL` - Database models
- `SCHEMA` - Schema classes (DANGEROUS)
- `DATABASE` - Database setup
- `MIGRATION` - Migration files
- `ROUTE` - Route files
- `NAMESPACE` - Publishable assets
- `PROVIDER` - Sub-providers
- `SERVICES` - Service bindings

## Safe Development Patterns

### Creating a New Module

```php
<?php

namespace Hanafalah\MyModule;

use Hanafalah\LaravelSupport\Providers\BaseServiceProvider;

class MyModuleServiceProvider extends BaseServiceProvider
{
    public function register()
    {
        // 1. Only register main class
        $this->registerMainClass(MyModule::class);

        // 2. Register services with closures (deferred loading)
        $this->app->singleton(MyService::class, function ($app) {
            return new MyService();
        });

        // 3. DON'T use registers(['*']) or registers(['Schema'])
    }

    public function boot()
    {
        // Safe to merge config here
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'my-module');
    }

    protected function dir(): string
    {
        return __DIR__ . '/';
    }
}
```

### Extending PackageManagement (Schema classes)

```php
<?php

namespace Hanafalah\MyModule\Schemas;

use Hanafalah\LaravelSupport\Supports\PackageManagement;

class MySchema extends PackageManagement
{
    protected $__config_name = 'my-module';

    // IMPORTANT: Don't call config() in constructor
    // Use lazy initialization instead
    public function getMyModel()
    {
        return $this->MyModel(); // Calls __callModel dynamically
    }
}
```

## Configuration Pattern

All modules should have config in `assets/config/config.php`:

```php
<?php

return [
    'namespace' => 'Hanafalah\\MyModule',
    'app' => [
        'contracts' => [
            // Contract => Implementation mappings
        ]
    ],
    'libs' => [
        'model' => 'Models',
        'contract' => 'Contracts',
        'schema' => 'Schemas',
    ],
    'database' => [
        'models' => [
            // 'ModelName' => Models\ModelName::class,
        ]
    ],
];
```

## Common Memory Issues & Fixes

### Issue: Memory exhausted on boot

**Symptom:**
```
PHP Fatal error: Allowed memory size of 536870912 bytes exhausted
in HasModelConfiguration.php on line 42
```

**Cause:** `registers(['*'])` or `registers(['Schema', 'Model'])`

**Fix:** Remove registers() call, register services manually

### Issue: Circular dependency

**Symptom:** Infinite loop or stack overflow

**Cause:** Class A imports Class B, Class B imports Class A

**Fix:** Use FQCN strings with lazy instantiation:
```php
// Bad
use MyModule\Facades\MyFacade;
MyFacade::doSomething();

// Good
$class = \MyModule\Facades\MyFacade::class;
$class::doSomething();
```

### Issue: Config not loaded

**Symptom:** `config('my-module.key')` returns null

**Cause:** Config merge happens after class tries to access it

**Fix:** Use `$this->app->booted()` callback for config-dependent code

## Testing Changes

After ANY change to laravel-support:

```bash
# Test in isolation first
cd repositories/laravel-support
composer dump-autoload

# Then test with backbone
docker exec -it wellmed-backbone php artisan config:clear
docker exec -it wellmed-backbone php artisan cache:clear
docker exec -it wellmed-backbone php artisan octane:reload

# Monitor for memory issues
docker logs wellmed-backbone 2>&1 | grep -i "memory\|fatal"
```

## Files to NEVER Auto-Load via registers()

These cause memory chains:
- `Schemas/*.php` - extend PackageManagement
- `Supports/PackageManagement.php` - uses HasModelConfiguration
- `Supports/BasePackageManagement.php` - base class
- Any class with `HasModelConfiguration` trait

## Dependencies

This package requires:
- `symfony/filesystem`
- `spatie/laravel-data`
- `stancl/jobpipeline`
- `hanafalah/laravel-stub`
- `hanafalah/laravel-has-props`
- `hanafalah/module-service`
- `spatie/laravel-medialibrary`

## Modification Checklist

Before modifying laravel-support:

- [ ] Change won't affect `registers()` method behavior
- [ ] No new trait uses that call `config()` during load
- [ ] No circular imports added
- [ ] Tested with `php artisan config:clear`
- [ ] Tested boot in wellmed-backbone container
- [ ] Memory stays under 512MB during boot
- [ ] All dependent modules still work

## Global Helper Functions

Defined in `src/helper.php`:
- `class_name_builder()` - Build class names
- `support_config_path()` - Get config path
- Other utility functions

These are auto-loaded via composer.json `files` array.
