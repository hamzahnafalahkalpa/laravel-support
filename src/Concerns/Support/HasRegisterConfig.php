<?php

namespace Hanafalah\LaravelSupport\Concerns\Support;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Database\Eloquent\Relations\Relation;

trait HasRegisterConfig
{

  protected function addDataToConfig(string $package_name, string $config_name, string $type){
    $plural_type      = Str::plural($type);
    $config_type      = "$config_name.$plural_type";
    if (!$this->checkCacheConfig('config-cache')){
        $config    = config($package_name);
        $morphMaps = config($config_type,[]);
        if (isset($config['libs'], $config['libs'][$type])) {
            $exploded = explode('\\', static::class);
            $prefix   = implode('\\', array_slice($exploded, 0, 2));
            $path     = (\method_exists($this,'basePath'))
                        ? $this->basePath() 
                        : $this->dir();
            $path     = $path.$config['libs'][$type];
            if (is_dir($path)){
                $files   = File::allFiles($path);
                $new_map = [];

                foreach ($files as $file) {
                    $relativePath = $file->getRelativePathname();
                    $className = $prefix.'\\'.$config['libs'][$type].'\\'.str_replace(['/', '.php'], ['\\', ''], $relativePath);
                    $new_map[class_basename($className)] = $className;
                }
            }
        }
        $package_morph = $this->mergeArray($new_map ?? [], $config[$config_name][$plural_type] ?? []);
        $morphMaps = $this->mergeArray($morphMaps, $package_morph ?? []);
        config([$config_type => $morphMaps]);
        config([$package_name.'.'.$config_type => $package_morph ?? []]);
    }else{
        $morphMaps = config($config_type, []);
    }
    if ($type == 'model') Relation::morphMap($morphMaps);
  }

  public function processRegisterProvider(string $config_name,?array $packages = [])
  {
    if (isset($packages) && count($packages) > 0) {
      foreach ($packages as $package) {
        $provider = $this->replacement($package['provider']);
        app()->register($provider);
        if(!$this->checkCacheConfig($config_name.'.packages')){
            $provider_basename = Str::replace('ServiceProvider', '', class_basename($provider));
            config(["$config_name.packages.$provider_basename.provider" => $provider]);
        }
      }
    }
  }  

  public function processRegisterConfig(string $config_name, ?string $additional_config_path = null)
  {
    if (!$this->checkCacheConfig('config-cache')){
        if (isset($additional_config_path)) {
          $configs = array_values(array_diff(scandir($additional_config_path), ['.', '..', 'config.php']));
          foreach ($configs as $config) {
              $path = $additional_config_path . DIRECTORY_SEPARATOR . $config;
              $this->basePathResolver($path);
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
        $laravel_encodings = config()->get('module-encoding.encodings') ?? [];
        config()->set('module-encoding.encodings', $this->mergeArray(
            $laravel_encodings,
            config()->get("$config_name.encodings") ?? []
        ));
    
    }
    if (config("$config_name.app.impersonate") !== null) {
        config()->set('app.impersonate', config("$config_name.app.impersonate"));
    }
  }

  public function checkCacheConfig(string $cached_config_name): bool
  {
    $cached_lists = config('app.cached_lists', []);
    return in_array($cached_config_name, $cached_lists);
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
      $config_root[] = $key;
      if ($this->isArray($value)) {
          foreach ($value as $k => $v) {
              $this->overrideConfig($k, $v, $config_root);
          }
      } else {
          $config_root = implode('.', $config_root);
          config()->set($config_root, $value);
      }
      if ($key == 'contextual_bindings') {
          $this->contextualBindings($value);
      }
  }

  protected function contextualBindings(callable|array $binds): self{
      if (\is_callable($binds)) {
          $binds = $binds();
      }
      if (isset($binds) && is_array($binds)){
          foreach ($binds as $key => $bind) {
              if (!isset($bind['from']) || !isset($bind['give'])) {
                  if (count($bind) == 2){
                      list($from,$give) = $bind;
                  }else{
                      continue;
                  }
              }else{
                  $from = $bind['from'];
                  $give  = $bind['give'];
              }

              $this->app->when($from)
                  ->needs($key)
                  ->give($give);
          }
      }
      return $this;
  }
}
