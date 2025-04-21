<?php

namespace Hanafalah\LaravelSupport\Supports;

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

/** 
 * @method static self useSchema(string $className)
 * @method static mixed callCustomMethod()
 * @method static self add(? array $attributes=[])
 * @method static self adds(? array $attributes=[],array $parent_id=[])
 * @method static array outsideFilter(array $attributes, array ...$data)
 * @method static self beforeResolve(array $attributes, array $add, array $guard = [])
 * @method static childSchema($schema,$callback)
 * @method static self change(array $attributes=[])
 * @method static escapingVariables(callable $callback,...$args)
 * @method static self fork(callable $callback)
 * @method static self child(ca\llable $callback)
 * @method static array createInit(array $adds, array $attributes, $guards = []) 
 * @method static self pushMessage(string $message)
 * @method static array getAppModelConfig()
 * @method static self setAppModels(array $models = [])
 * @method static Model|null getModel(string $model_name = null)
 * @method static self setModel($model=null)
 * @method static bool isRecentlyCreated($model = null)
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
    public static $param_logic = 'and';
    protected array $__resources = [];
    protected array $__schema_contracts = [];

    /**
     * Constructor method for initializing the PackageManagement class.
     *
     * @param Container $app The container instance for dependency injection
     */
    public function __construct(
        ...$args
    ) {
        $this->setLocalConfig('laravel-support');
        $this->__schema_contracts = config('app.contracts', []);
    }

    protected function fillingProps(Model &$model, mixed $props, ?array $onlies = []){
        foreach ($props as $key => $prop) {
            if ($key == 'props'){
                $this->fillingProps($model, $prop);
            }else{
                if (count($onlies) > 0 && in_array($key, $onlies)){
                    $model->{$key} = $prop;
                }else{
                    $model->{$key} = $prop;
                }
            }
        }
    }

    public function schemaContract(string $contract)
    {
        $contract = Str::studly($contract);
        return app(config('app.contracts.' . $contract));
    }

    public function usingEntity(): Model{
        return $this->{$this->__entity.'Model'}();
    }

    public function viewEntityResource(callable $callback,array $options = []): array{
        return $this->transforming($this->usingEntity()->getViewResource(),function() use ($callback){
            return $callback();
        },$options);
    }

    public function showEntityResource(callable $callback,array $options = []): array{
        return $this->transforming($this->usingEntity()->getShowResource(),function() use ($callback){
            return $callback();
        },$options);
    }

    public function myModel(?Model $model = null)
    {
        $model = $this->model ??= $model;
        if (isset($model)) $this->setModel($model);
        return $model;
    }

    public function setParamLogic(string $logic): self
    {
        static::$param_logic = $logic;
        return $this;
    }

    public function getParamLogic(): string{
        return static::$param_logic;
    }

    /**
     * Sets the class for the SetupManagement instance.
     *
     * @param string $className The class name to set.
     * @return self Returns the SetupManagement instance.
     */
    public function useSchema(string $className): DataManagement
    {
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
    public function flushTagsFrom(string|array $category, ?string $tags = null, ?string $suffix = null)
    {
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

    /**
     * Call the given schema with the given callback function.
     *
     * @param string $schema The schema to be called.
     * @param callable $callback The callback function to be called.
     * @return void
     */
    protected function childSchema($schema, $callback)
    {
        $this->child(function ($parent) use ($schema, $callback) {
            $schema = is_string($schema) ? app($schema) : $schema;
            $schema->booting();
            $callback($schema, $parent);
        });
    }

    public function booting(): self
    {
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
    public function callCustomMethod(): mixed
    {
        return ['Model', 'Configuration', 'Method'];
    }

    /**
     * Forks the current instance of the PackageManagement class and applies the
     * given callback to the forked instance.
     *
     * The callback will be called with the forked instance as the argument.
     *
     * After the callback is called, the forked instance will be reverted to the
     * original instance.
     *
     * @param callable $callback The callback to call with the forked instance.
     *
     * @return self Returns the original instance.
     */
    public function fork(callable $callback): self
    {
        $this->escapingVariables(function ($class) use ($callback) {
            $callback($class);
        }, static::class);
        return $this;
    }

    /**
     * Forks the current instance of the PackageManagement class and applies the
     * given callback to the forked instance.
     *
     * The callback will be called with the forked instance as the argument.
     *
     * After the callback is called, the forked instance will be reverted to the
     * original instance.
     *
     * @param callable $callback The callback to call with the forked instance.
     *
     * @return self Returns the original instance.
     */
    public function child(callable $callback): self
    {
        $this->escapingVariables(function ($parent) use ($callback) {
            $callback($parent);
        }, self::$__class);
        return $this;
    }
}
