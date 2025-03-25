<?php

namespace Hanafalah\LaravelSupport\Providers;

use Exception;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\{
    Facades\Config,
    ServiceProvider
};
use Illuminate\Support\Facades\App;
use Hanafalah\LaravelSupport\Concerns\{
    DatabaseConfiguration as Database,
    ServiceProvider as Service,
    Support
};
use Illuminate\Support\Facades\File;

use Hanafalah\LaravelSupport\Concerns\PackageManagement\HasEvent;

use Illuminate\Support\Str;
use Hanafalah\LaravelSupport\Enums\Provider\ProviderRegisterMethod;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;

abstract class BaseServiceProvider extends ServiceProvider
{
    use Support\HasCall;
    use Database\HasDatabaseConfiguration;
    use Service\HasRouteConfiguration;
    use Service\HasConfiguration;
    use Service\HasMigrationConfiguration;
    use Service\HasProviderConfiguration;
    use Support\HasRepository;
    use Support\HasMicrotenant;
    use Support\HasArray;
    use HasEvent;

    protected string $__lower_package_name,
        $__main_class,
        $__class_basename,
        $__command_service_provider,
        $__route_service_service_provider,
        $__migration_path = '',
        $__target_migration_path = '';
    protected array $__finished_register = [];

    public array $__events = [];

    /**
     * Constructor method.
     *
     * @param Container $app The application instance.
     *
     * @return void
     */
    public function __construct(Container $app)
    {
        parent::__construct($app);
        $this->__config  = $app['config'];
        $class_base_name = \class_name_builder(Str::replace('ServiceProvider', '', $this::class));
        $this->setClassBaseName($class_base_name);
        $this->setLowerPackageName(\class_name_builder($class_base_name));
    }

    public function events(): array
    {
        return $this->__events;
    }

    protected function bootedRegisters(Model $model, string $config_name, ?string $migration_path = null): self
    {
        if (isset($migration_path)) {
            if (isset($this->__config[$config_name]['libs']) && isset($this->__config[$config_name]['libs']['migration'])) {
                $migration_path = $this->dir() . '/../' . $this->__config[$config_name]['libs']['migration'];
                $this->overrideDatabasePath($migration_path);
            } else {
                new Exception('Migration path not found');
            }
        }

        $this->registerProvider(function () use ($model, $config_name) {
            $packages = $model->packages;
            if (isset($packages)) {
                foreach ($packages as $key => $package) {
                    $provider = $this->replacement($package['provider']);
                    $this->app->register($provider);
                    $provider_basename = Str::replace('ServiceProvider', '', class_basename($provider));
                    config(["$config_name.packages.$provider_basename.provider" => $provider]);
                }
            }
        });
        return $this;
    }

    /**
     * Sets the database migration path to the given path.
     *
     * @param string $migration_path The path to set as the database migration path.
     *
     * @return self
     */
    public function overrideDatabasePath(string $migration_path): self
    {
        App::useDatabasePath($migration_path);
        return $this;
    }

    /**
     * Registers a configuration override for the given package config name.
     *
     * The method will loop through all packages under the given config name and
     * override the config using the config key and the package config.
     *
     * @param string $config_name The config name to override.
     *
     * @return self
     */
    protected function registerOverideConfig(string $config_name, ?string $additional_config_path = null): self
    {
        $this->registerConfig(function () use ($config_name, $additional_config_path) {
            if (isset($additional_config_path)) {
                $configs = array_diff(scandir($additional_config_path), ['.', '..', 'config.php']);
                foreach ($configs as $config) {
                    $path = $additional_config_path . '/' . $config;
                    if (is_file($path)) {
                        $content = include $path;
                        $this->overrideConfig(Str::replace('.php', '', $config), $content);
                    }
                }
            }

            $packages  = config()->get("$config_name.packages");
            if (isset($packages)) {
                foreach ($packages as $key => $package) {
                    $key = Str::snake($key, '-');
                    $this->overrideConfig($key, $package['config'] ?? config($key));
                }
            }
            $laravel_encodings = config()->get('laravel-support.encodings') ?? [];
            config()->set('laravel-support.encodings', $this->mergeArray(
                $laravel_encodings,
                config()->get("$config_name.encodings") ?? []
            ));
        });
        return $this;
    }

    /**
     * Recursively overrides configuration values.
     *
     * This method traverses the provided value, and if it's an array, it recursively
     * calls itself to handle nested configuration. If the value is not an array, it
     * sets the configuration at the computed key path.
     *
     * @param string $key The key for the current config value.
     * @param mixed $value The config value to be set, which can be nested.
     * @param array $config_root The root path of the config key, allows for nested structure.
     */
    protected function overrideConfig(string $key, mixed $value, array $config_root = [])
    {
        $key = Str::studly($key);
        $config_root[] = $key;
        if ($this->isArray($value)) {
            foreach ($value as $k => $v) {
                $this->overrideConfig($k, $v, $config_root);
            }
        } else {
            $config_root = implode('.', $config_root);
            config()->set($config_root, $value);
            config()->set('app.contracts.' . $key, $value);
        }
    }

    /**
     * Sets the class base name of the package.
     *
     * This method simply sets the class base name of the package to the given name.
     * The class base name is the last part of the fully qualified class name, without
     * the namespace.
     *
     * @param string $name The class base name of the package.
     *
     * @return self
     */
    protected function setClassBaseName(string $name): self
    {
        $this->__class_basename = class_basename($name);
        return $this;
    }

    /**
     * Sets the lower package name of the package.
     *
     * This method takes a class base name and sets the lower package name of the package
     * to the given name, converted to kebab case.
     *
     * @param string $name The lower package name of the package.
     *
     * @return self
     */
    protected function setLowerPackageName(string $name): self
    {
        $this->__lower_package_name = Str::kebab($name);
        return $this;
    }

    /**
     * Registers the main class of the package.
     *
     * This method takes a class base name, sets the lower package name of the package
     * to the given name, converted to kebab case, and stores the given class base name
     * in the instance property.
     *
     * If the given class base name has a method `events`, it will get the events data
     * from that method and merge it with the existing events data in the instance property.
     *
     * Finally, it will boot the events.
     *
     * @param mixed $main_class The class base name of the package.
     *
     * @return self
     */
    protected function registerMainClass(mixed $main_class): self
    {
        $this->__main_class         = $main_class;
        $this->__finished_register  = [];
        $this->setClassBaseName($main_class)
            ->setLowerPackageName($this->__class_basename);

        $this->registerConfig();
        $this->addDataToConfig('app','contract');
        $this->autoBinds();

        if (\method_exists('events', $main_class)) {
            //GET EVENTS DATA
            $main_class = app($main_class);
            $events = $main_class->events();
            $this->__events = $this->mergeArray($this->__events, $events);
        }

        $this->bootEvents();
        return $this;
    }

    /**
     * Registers a command service provider.
     *
     * This method takes a command service provider class and registers it
     * within the application. The command service provider is responsible
     * for registering and managing command-line commands associated with
     * the package.
     *
     * @param mixed $command The command service provider class to be registered.
     *
     * @return self
     */
    protected function registerCommandService(mixed $command): self
    {
        $this->app->register($command);
        return $this;
    }

    /**
     * Registers a route service provider.
     *
     * This method takes a route service provider class and registers it
     * within the application. The route service provider is responsible
     * for registering and managing the routes associated with the package.
     *
     * @param mixed $route The route service provider class to be registered.
     *
     * @return self
     */
    protected function registerRouteService(mixed $route_service): self
    {
        $this->app->register($route_service);
        return $this;
    }

    /**
     * Registers a migration for the package.
     *
     * This method takes an optional closure argument. If the closure is provided, it
     * is called and should return an array with two keys: 'path' and 'target'. The 'path'
     * key should point to the migration file to be registered, and the 'target' key should
     * point to the target migration file path. If the closure is not provided, the method
     * will do nothing.
     *
     * @param callable|null $callback The closure to be called, which should return an array
     *                                with 'path' and 'target' keys.
     *
     * @return self
     */
    protected function registerMigration(?callable $callback = null): self
    {
        if (isset($callback)) {
            $value = $callback();
            $this->__migration_path        = $value['path'] ?? '';
            $this->__target_migration_path = $value['target'] ?? '';
        }
        $this->setFinishedRegister(ProviderRegisterMethod::MIGRATION->value);
        return $this;
    }

    /**
     * Registers the route service provider.
     *
     * This method takes an optional closure argument. If the closure is provided, it
     * is called. If the closure is not provided, the method will do nothing.
     *
     * @param callable|null $callback The closure to be called.
     *
     * @return self
     */
    protected function registerRoute(?callable $callback = null): self
    {
        $this->mergeRoutes();
        $this->callMeBack($callback);
        $this->setFinishedRegister(ProviderRegisterMethod::ROUTE->value);
        return $this;
    }

    /**
     * Registers a list of services.
     *
     * This method takes an array of strings. Each string should be a valid case of
     * the ProviderRegisterMethod enum. If the string is '*', it will register all
     * services. If the string is not '*', it will register the service with that name.
     * If the string is a number, it will register the service with that index.
     *
     * The second argument is an array of strings. Each string should be a valid case
     * of the ProviderRegisterMethod enum. If the string is present in the second
     * argument, the service with that name will not be registered.
     *
     * @param string|array $args The array of strings to register.
     * @param string|array $excepts The array of strings to not register.
     *
     * @return self
     */
    protected function registers(string|array $args, string|array $excepts = []): self
    {
        $args       = $this->mustArray($args);
        $excepts    = $this->mustArray($excepts);
        $validation = !$this->inArray(ProviderRegisterMethod::CONFIG, $this->__finished_register) && !$this->inArray('Config', $excepts);
        if ($validation) $this->registerConfig();
        $hasAll   = false;
        foreach ($args as $key => $list) {
            if ($list !== '*') {
                $key = $this->registerName(($isNumber = is_numeric($key)) ? $list : $key);
                if ($this->inArray($key, $this->__finished_register)) continue;
                $this->{'register' . $key}(!$isNumber ? $list : null);
            } else {
                $hasAll = true;
            }
        }

        if ($hasAll) {
            $args = $this->mapArray(fn($case) => $case->value, ProviderRegisterMethod::cases());
            $args = $this->diff($args, $this->__finished_register[\class_basename($this)], $excepts);
            foreach ($args as $arg) {
                if (method_exists($this, 'register' . $arg)) {
                    $this->{'register' . $arg}();
                }
            }
        }
        return $this;
    }

    /**
     * Register a name of a service.
     *
     * This method takes a string and convert it to a valid case of the
     * ProviderRegisterMethod enum. It will convert the string to lower case
     * and then capitalize the first letter of the string.
     *
     * @param string $name The name of the service to register.
     *
     * @return string The name of the service in a valid case.
     */
    private function registerName(string $name): string
    {
        return ucfirst(\strtolower($name));
    }

    protected function autoBinds(): self{
        $contracts     = config($this->__lower_package_name.'.app.contracts', []);
        $contract_name = config($this->__lower_package_name.'.libs.contract','Contracts');
        $schema_name   = config($this->__lower_package_name.'.libs.schema','Schemas');
        foreach ($contracts as $key => $contract) {
            $schema_namespace = Str::replace($contract_name,$schema_name,$contract,true);
            $this->binds([$contract => $schema_namespace]);
        }
        return $this;
    }

    protected function binds(array $binds)
    {
        foreach ($binds as $key => $bind) {
            $this->app->bind($key, function ($app) use ($bind) {
                if (is_callable($bind)) {
                    return $bind($app);
                }
                if (is_object($bind)) return $bind;
                if (is_string($bind)) return new $bind($app);
            });
        }
    }

    /**
     * Registers a callback to be called when the application is booting.
     *
     * This method takes a callable that will be called when the application
     * is booting. The callback will be passed the application instance as an
     * argument.
     *
     * @param callable|null $callback The callback to be called.
     *
     * @return self
     */
    protected function appBooting(?callable $callback): self
    {
        $this->app->booting(function ($app) use ($callback) {
            $callback($app);
        });
        return $this;
    }

    /**
     * Registers a callback to be called when the application has booted.
     *
     * This method takes a callable that will be called when the application
     * has booted. The callback will be passed the application instance as an
     * argument.
     *
     * @param callable|null $callback The callback to be called.
     *
     * @return self
     */
    protected function appBooted(?callable $callback): self
    {
        $this->app->booted(function ($app) use ($callback) {
            $callback($app);
        });
        return $this;
    }

    /**
     * Gets the custom methods to be called after the package has been registered.
     *
     * This method should return an array of strings, which are the names of the
     * methods to be called.
     *
     * @return array
     */
    public function callCustomMethod()
    {
        return ['Model', 'Configuration'];
    }

    abstract protected function dir(): string;

    /**
     * Gets the paths of the views that will be published.
     *
     * @param string $lowerClassName
     * @return array
     */
    protected function getPublishableViewPaths($lowerClassName): array
    {
        $paths = [];
        foreach (Config::get('view.paths') as $path)
            if (is_dir($path . '/' . $lowerClassName)) $paths[] = $path . '/' . $lowerClassName;
        return $paths;
    }

    /**
     * Sets the morph map for the Relation class.
     *
     * @param array $morphs The array of morphs to set.
     * @return self The current instance of the class.
     */
    protected function morphMap($morphs = []): self
    {
        Relation::morphMap($morphs);
        return $this;
    }

    /**
     * Calls the callback function if it is set.
     *
     * This method is used to call any custom methods that need to be called
     * after the package has been registered.
     *
     * @param callable|null $callback The callback function to call.
     * @return self The current instance of the class.
     */
    private function callMeBack(?callable $callback = null): self
    {
        if (isset($callback)) $callback();
        return $this;
    }

    /**
     * Marks a service as registered in the finished register list.
     *
     * This method takes a string representing the service to be registered and appends
     * it to the finished register list specific to the current class. The list is stored
     * in an associative array indexed by the class's base name.
     *
     * @param string $register The name of the service to be marked as registered.
     */
    private function setFinishedRegister(string $register)
    {
        $class_base_name = \class_basename($this);
        if (!isset($this->__finished_register[$class_base_name])) {
            $this->__finished_register[$class_base_name] = [];
        }
        $this->__finished_register[$class_base_name][] = $register;
    }

    /**
     * Merge the array from URL parameters into the request object.
     *
     * This function checks if the current request has a route associated with it.
     * If it does, it retrieves the parameters from the route and merges them into
     * the request object.
     *
     * @return self
     */
    protected function paramSetup(): self
    {
        //MERGIN ARRAY FROM URL PARAMS
        if (request()->route()) {
            $parameters = request()->route()->parameters();
            request()->merge($parameters);
        }
        if (isset(request()->per_page)) {
            request()->merge([
                'perPage' => request()->per_page
            ]);
        }
        return $this;
    }

    /**
     * Registers the CommandServiceProvider with the application.
     *
     * @return $this The current instance of the class.
     */
    protected function registerProvider(?callable $callback = null): self
    {
        $this->callMeBack($callback);
        $this->setFinishedRegister(ProviderRegisterMethod::PROVIDER->value);
        return $this;
    }

    /**
     * Registers the morph map for the models.
     *
     * @return $this The current instance of the class.
     */
    protected function registerModel(?callable $callback = null): self
    {
        if ($this->isExistsDatabaseModel()) {
            $this->callMeBack($callback);
            $this->addDataToConfig('database','model');
        }
        $this->setFinishedRegister(ProviderRegisterMethod::MODEL->value);
        return $this;
    }

    protected function addDataToConfig(string $config_name,string $type){
        $config           = $this->__config[$this->__lower_package_name];
        $plural_type      = Str::plural($type);
        $config_type      = "$config_name.$plural_type";
        $morphMaps        = config($config_type,[]);
        if (isset($config['libs'], $config['libs'][$type])) {
            $exploded = explode('\\', static::class);
            $prefix   = implode('\\', array_slice($exploded, 0, 2));
            $path     = (\method_exists($this,'basePath'))
                        ? $this->basePath() 
                        : $this->dir();
            $files = File::allFiles($path.$config['libs'][$type]);
            $new_map = [];
            foreach ($files as $file) {
                $relativePath = $file->getRelativePathname();
                $className = $prefix.'\\'.$config['libs'][$type].'\\'.str_replace(['/', '.php'], ['\\', ''], $relativePath);
                $new_map[class_basename($className)] = $className;
            }
        }
        $package_morph = $this->mergeArray($new_map ?? [], $config[$config_name][$plural_type] ?? []);
        config([$config_type => $this->mergeArray($morphMaps, $package_morph ?? [])]);
        config([$this->__lower_package_name.'.'.$config_type => $package_morph ?? []]);
    }

    /**
     * Register deferred providers based on the given model.
     *
     * @param Model $model The model instance to check and register providers for.
     */
    // public function deferredProviders(Model $model)
    // {
    //     $this->app->register($this->replacement($model->app['provider']));
    //     $this->app->register($this->replacement($model->group['provider']));
    // }

    /**
     * Replaces multiple backslashes with a single backslash in the given string.
     *
     * @param string $value The string to process.
     * @return string The processed string with reduced backslashes.
     */
    private function replacement(string $value)
    {
        return preg_replace('/\\\\+/', '\\', $value);
    }

    /**
     * Registers the configuration for the package.
     *
     * This method merges and sets the local configuration for the package
     * identified by the lower package name. An optional callback can be executed.
     *
     * @param callable|null $callback The callback to be executed.
     * @return self The current instance of the class.
     */
    public function registerConfig(?callable $callback = null): self
    {        
        if (isset($this->__lower_package_name)) {
            $this->mergeConfigWith($this->__lower_package_name)
                ->setLocalConfig($this->__lower_package_name);
        }
        // if (isset($this->__config[$this->__lower_package_name]['contracts'])) {
        //     $general_contracts = config('app.contracts', []);
        //     $contracts = $this->__config[$this->__lower_package_name]['contracts'];
        //     config(['app.contracts' => $this->mergeArray($general_contracts, $contracts)]);
        // }

        $this->callMeBack($callback);
        $this->setFinishedRegister(ProviderRegisterMethod::CONFIG->value);
        return $this;
    }

    /**
     * Registers the database models for the package.
     *
     * This method checks if database models exist and sets them within the application.
     * An optional callback can be executed after registering the models.
     *
     * @param callable|null $callback The callback to be executed.
     * @return self The current instance of the class.
     */
    protected function registerDatabase(?callable $callback = null): self
    {
        // if ($this->isExistsDatabaseModel()) {
        //     $this->setAppModels($this->__config[$this->__lower_package_name]['database']['models']);
        // }
        $this->callMeBack($callback);
        $this->setFinishedRegister(ProviderRegisterMethod::DATABASE->value);
        return $this;
    }

    /**
     * Checks if database models exist for the package.
     *
     * @return bool True if database models exist, false otherwise.
     */
    private function isExistsDatabaseModel(): bool
    {
        $config = $this->__config[$this->__lower_package_name];
        return isset($config['database']) && isset($config['database']['models']);
    }

    /**
     * Publishes the config and stub files to the application.
     *
     * @return self The current instance of the class.
     */
    public function registerNamespace(?callable $callback = null): self
    {

        $this->publishes([
            $this->getConfigFullPath() => config_path($this->__lower_package_name . '.php'),
        ], 'config');

        $this->publishes([
            $this->getAssetPath('stubs') => base_path('Stubs/' . $this->__class_basename . 'Stubs'),
        ], 'stubs');

        $this->publishes($this->scanForPublishMigration($this->__migration_path, $this->__target_migration_path), 'migrations');

        $this->callMeBack($callback);
        $this->setFinishedRegister(ProviderRegisterMethod::NAMESPACE->value);
        return $this;
    }

    /**
     * Registers the services provided by this package with the application.
     *
     * @return self The current instance of the class.
     */
    protected function registerServices(?callable $callback = null): self
    {
        $this->app->singleton($this->__main_class);
        $this->callMeBack($callback);
        return $this;
    }
}
