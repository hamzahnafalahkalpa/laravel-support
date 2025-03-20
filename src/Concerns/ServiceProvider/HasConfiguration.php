<?php

namespace Hanafalah\LaravelSupport\Concerns\ServiceProvider;

use Hanafalah\LaravelSupport\Concerns;
use Illuminate\Support\Str;

/**
 * @mixin \Hanafalah\LaravelSupport\BaseServiceProvider
 */
trait HasConfiguration
{
    use Concerns\Support\HasLocalDir;
    use Concerns\Support\HasCall;

    /**
     * @var string
     */
    protected string $__config_file_name = 'config';

    /**
     * @var string
     */
    protected string $__config_base_path = '../assets/config';

    /**
     * Global configuration that is owned by the laravel application.
     * 
     * @var array
     */
    protected \Illuminate\Config\Repository $__config;

    /**
     * Local configuration that is used in one environment command package.
     * 
     * This variable is used to store a configuration that is specific to one environment command package.
     * You can use this variable to pair it with the `EnvironmentCommand` in each package so that you have a generic config variable in each command in the same environment.
     * 
     * @var array
     */
    protected array $__local_config = [];

    protected string $__local_config_name;

    /**
     * Cross package configuration that is used in multiple environment command package.
     * 
     * This variable is used to store a configuration that is used in multiple environment command package.
     * You can use this variable to pair it with the `EnvironmentCommand` in each package so that you have a generic config variable in each command in the same environment.
     * 
     * @var array
     */
    protected static array $__cross_config = [];

    /**
     * This method is called when the magic `__call` is called with a method name that ends with 'Config'.
     * It will try to find the configuration by the given property name and return the configuration value.
     * If the property name is not found, it will return null.
     * 
     * @return mixed|null
     */
    public function __callConfiguration()
    {
        $method = $this->getCallMethod();
        if (!Str::startsWith($method, 'Config') && Str::endsWith($method, 'Config')) {
            $property = Str::snake($method);
            $configs  = $this->{'__' . $property};
            if (isset($configs)) {
                $roots = explode('.', $this->__call_arguments[0]);
                foreach ($roots as $root) $configs = isset($configs[$root]) ? $configs[$root] : [];
                $var = $this->{'__' . Str::snake($property)};
                if (isset($var)) return $var;
            }
        }
    }

    /**
     * @return $this
     */
    protected function initConfig(): self
    {
        $this->__config = config();
        return $this;
    }

    /**
     * @param string|null $root
     * @return array
     */
    protected function config(?string $root = null): array
    {
        if (isset($root)) config($root);
        $this->initConfig();
        return $this->__config;
    }

    /**
     * @param string $root
     * @param array $config
     * @return $this
     */
    protected function setConfig(string $root, array &$config): self
    {
        $config = $this->__config[$root] ?? [];
        return $this;
    }

    /**
     * @param string $alias
     * @param string|null $path
     * @param string|null $base_path
     * @return $this
     */
    public function mergeConfigWith(string $alias, ?string $path = null, ?string $base_path = null): self
    {
        $base_path ??= $this->getConfigFullPath($path);
        $local_config = include $base_path;
        $this->injectLocalConfig($alias, $local_config);
        $this->mergeConfigFrom($base_path, $alias);
        $this->initConfig();

        $general_contracts = config('app.contracts', []);
        $local_contracts   = config($alias . '.contracts', []);
        config(['app.contracts' => $this->mergeArray($general_contracts, $local_contracts)]);
        return $this;
    }

    protected function injectLocalConfig(string $key, mixed $value, array $config_root = [])
    {
        $config_root[] = $key;
        if ($this->isArray($value)) {
            foreach ($value as $k => $v) {
                $this->injectLocalConfig($k, $v, $config_root);
            }
        } else {
            $config_root = implode('.', $config_root);
            $config_value = config()->get($config_root);
            if (!isset($config_value)) {
                config()->set($config_root, $value);
            }
        }
    }

    /**
     * @return string
     */
    protected function getConfigBasePath(): string
    {
        return $this->__config_base_path;
    }

    /**
     * @param string|null $path
     * @return string
     */
    protected function getConfigFullPath(?string $path = null): string
    {
        return $this->dir() . $this->getConfigBasePath() . '/' . ($path ??= $this->__config_file_name) . '.php';
    }

    /**
     * @param string $config_name
     * @return $this
     */
    protected function setLocalConfig(string $config_name): self
    {
        $this->setLocalConfigName($config_name)
            ->setConfig($config_name, $this->__local_config);
        return $this;
    }

    protected function setLocalConfigName(string $config_name): self
    {
        $this->__local_config_name = $config_name;
        return $this;
    }

    /**
     * @param array $config
     * @return $this
     */
    protected function setCrossConfig(array $config): self
    {
        self::$__cross_config = $config;
        return $this;
    }
}
