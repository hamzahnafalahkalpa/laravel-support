<?php

namespace Zahzah\LaravelSupport\Concerns\DatabaseConfiguration;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Zahzah\LaravelSupport\Concerns\Support\HasCall;
use Illuminate\Support\Str;

trait HasModelConfiguration{
    use HasCall;

    public static $__model;
    public static array $__models_config = [];

    /**
     * This method is used to call dynamic methods that are defined in the current model.
     *
     * The method will first check if the method is a model method, and if so, it will
     * call the model method.
     *
     * For example, if you call `UserModel` and the value of `UserModel` in
     * the 'database.models' config is 'App\Models\User', the method will return a
     * new instance of `App\Models\User`.
     *
     * @return mixed|null
     */
    public function __callModel(){
        $method = $this->getCallMethod();
        $isInstance = Str::endsWith($method,'ModelInstance');
        $isMorph    = Str::endsWith($method,'ModelMorph');
        if (($method !== 'Model' && Str::endsWith($method, 'Model')) || $isInstance || $isMorph) {
            $firstMethod = Str::of($method)->beforeLast('Model');
            $modelName   = config('database.models.'.$firstMethod);
            if (isset($modelName)) {
                if ($isInstance){
                    return $modelName;
                }else{
                    self::$__model = new $modelName();
                    return ($isMorph) ? self::$__model->getMorphClass() : self::$__model;
                }
            }
        }
    }

    /**
     * Retrieves the models configuration associated with the current instance.
     *
     * @return array The models configuration associated with the current instance.
     */
    protected function getAppModelConfig(): array{
        if (count(static::$__models_config) == 0) static::$__models_config = config('database.models') ?? [];
        return static::$__models_config;
    }

    /**
     * 
     * Sets the models configuration associated with the current instance.
     *
     * Merges the given models configuration with the existing one, and sets the
     * result as the new models configuration associated with the current instance.
     *
     * @param array $models The models configuration to set.
     *
     * @return static The current instance.
     */

    protected function setAppModels(array $models = []): self{
        config([
            'database.models' => static::$__models_config = $this->mergeArray($this->getAppModelConfig(),$models)
        ]);
        return $this;
    }


    /**
     * Retrieves a model based on the provided model name or returns the current model instance.
     *
     * If a specific model name is provided, it returns the corresponding model from the configuration.
     * Otherwise, it returns the current model instance. If the initialization of the model is implemented
     * and applicable, it will be called before returning the model.
     *
     * @param bool $new Indicates whether to initialize a new model instance.
     * @param string|null $model_name The name of the model to retrieve from the configuration.
     *
     * @return Model|null The model instance or the model from the configuration.
     */
    public function getModel(bool $new = false,string $model_name = null):? Model{
        if (count(static::$__models_config) == 0) $this->getAppModelConfig();
        return (isset($model_name)) 
            ? static::$__models_config[$model_name] 
            : self::$__model;
    }
    
    /**
     * Sets the model name associated with the current instance.
     *
     * @param mixed $model The model name or the model instance to set.
     *
     * @return static The current instance.
     */
    protected function setModel(mixed $model=null): self{
        if (isset($model)) {
            $condition = $model instanceof Model ||
                         $model instanceof LengthAwarePaginator ||
                         $model instanceof Collection;
            static::$__model = ($condition) ? $model : $this->{$model.'Model'}();
        }
        return $this;
    }

    /**
     * Determines if the model has been recently created.
     *
     * @return bool
     */
    protected function isRecentlyCreated($model = null): bool{
        $model = $model ?? self::$__model;
        return isset($model) ? $model->wasRecentlyCreated : false;
    }
}