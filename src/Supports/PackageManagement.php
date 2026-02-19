<?php

namespace Hanafalah\LaravelSupport\Supports;

use Carbon\CarbonTimeZone;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Traits\ForwardsCalls;
use Hanafalah\LaravelSupport\{
    Concerns\Support,
    Concerns\PackageManagement as Package
};
use Illuminate\Support\Str;
use Hanafalah\LaravelSupport\Concerns\Support\HasCache;
use Hanafalah\LaravelSupport\Concerns\Support\Macroable;
use Hanafalah\LaravelSupport\Contracts\Supports\DataManagement;
use Illuminate\Support\Carbon;
use stdClass;

/** 
 * @method static self useSchema(string $className)
 * @method static mixed callCustomMethod()
 * @method static Model|null getModel(string $model_name = null)
 * @method static self setModel($model=null)
 */

abstract class PackageManagement extends BasePackageManagement implements DataManagement
{
    use Support\HasResponse,
        Support\HasRequest,
        Support\HasArray,
        Support\HasJson,
        Support\HasCallStatic,
        Support\HasGoogleTranslate,
        Package\ResponseModifier,
        Package\DataManagement,
        Package\HasCallMethod,
        Package\HasInitialize,
        Package\HasEvent,
        HasCache;
    use ForwardsCalls;

    public $initialized = false;
    public $instance;
    protected array $__resources = [];
    protected ?array $__schema_contracts = null; // Lazy loaded
    public bool $is_recently_created = false;

    /**
     * Constructor method for initializing the PackageManagement class.
     * Optimized: No config loading in constructor to prevent memory issues.
     *
     * @param Container $app The container instance for dependency injection
     */
    public function __construct(
        // ...$args
    ) {
        // Defer config loading to avoid memory exhaustion during boot
        // Config will be loaded lazily when needed via getSchemaContracts()
    }

    /**
     * Get schema contracts with lazy loading.
     *
     * @return array
     */
    protected function getSchemaContracts(): array
    {
        if ($this->__schema_contracts === null) {
            $this->setLocalConfig('laravel-support');
            $this->__schema_contracts = config('app.contracts', []);
        }
        return $this->__schema_contracts;
    }

    protected function fillingProps(object &$model, mixed $props = [], ?array $onlies = []){
        $props ??= [];
        foreach ($props as $key => $prop) {
            if (str_contains($key, 'search_') || method_exists($model, $key)) continue;
            if ($prop instanceof Carbon) {
                $model->{$key} = $prop->toDateTimeString();
                continue;
            }
            if ($key == 'props'){
                $this->fillingProps($model, $prop);
            }else{
                if (is_object($prop) && method_exists($prop, 'toArray')){
                    $model->{$key} = new stdClass();
                    $this->fillingProps($model->{$key}, $prop->toArray());
                }else{
                    if (is_array($prop)) {
                        $model->{$key} = new stdClass();
                        $this->fillingProps($model->{$key}, $prop);
                    }else{
                        if (count($onlies) > 0 && in_array($key, $onlies)){
                            $model->{$key} = $prop;
                        }else{
                            $model->{$key} = $prop;
                        }
                    }
                }
            }
        }
    }

    public function schemaContract(string $contract){
        $contract = Str::studly($contract);
        $contracts = $this->getSchemaContracts();
        if (!array_key_exists($contract, $contracts)) {
            throw new \Exception("Contract '$contract' not found in config 'app.contracts'");
        }
        return app($contracts[$contract] ?? config('app.contracts.' . $contract));
    }

    public function myModel(?Model $model = null){
        $model = $this->model ??= $model;
        if (isset($model)) $this->setModel($model);
        return $model;
    }

    /**
     * Sets the class for the SetupManagement instance.
     *
     * @param string $className The class name to set.
     * @return self Returns the SetupManagement instance.
     */
    public function useSchema(string $className): DataManagement{
        $this->setClass($className);
        return $this;
    }

    /**
     * Forgets cache using the given category.
     *
     * @param string $category The category of the cache to forget.
     *
     * @throws \Exception
     *
     * @return void
     */
    public function flushTagsFrom(string|array $category, ?string $tags = null, ?string $suffix = null){
        if (is_array($category)) {
            foreach ($category as $key => $cat) {
                if (is_numeric($key)) {
                    $this->flushTagsFrom($cat);
                } else {
                    $this->flushTagsFrom($key, $cat['tags'] ?? null, $cat['suffix'] ?? null);
                }
            }
        } else {
            if (isset($tags) && isset($suffix)) $this->addSuffixCache($this->__cache[$category], $tags, $suffix);
            if (isset($this->__cache[$category])) {
                $this->forgetTags($this->__cache[$category]['tags']);
            } else {
                throw new \Exception('Cache using ' . $category . ' not found', 422);
            }
        }
    }

    public function booting(): self{
        $this->instance  = new static;
        static::$__class = $this;
        static::$__model = $this->{$this->__entity . 'Model'}();
        return $this;
    }

    /**
     * Calls the custom method for the current instance.
     *
     * It will first check if the method is a model method, and if so, it will
     * call the model method.
     *
     * @return mixed|null
     */
    public function callCustomMethod(): mixed{
        return ['Model', 'Configuration', 'Method', 'SchemaEloquent'];
    }
}