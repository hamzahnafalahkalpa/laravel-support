<?php

namespace Hanafalah\LaravelSupport\Concerns\PackageManagement;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Hanafalah\LaravelSupport\Exceptions\ModelCouldNotBeIdentifiedByid;
use Hanafalah\LaravelSupport\Events;

use Illuminate\Support\Str;

trait HasInitialize
{
    private ?Model $__initialize_model;

    private string $__ending_event, $__ended_event;
    protected string $__entity = '';

    /**
     * The events that are fired by the workspace.
     *
     * @return array
     */
    public function events()
    {
        return [
            Events\InitializingEvent::class => [],
            Events\EventInitialized::class  => [],
            Events\EndingEvent::class       => [],
            Events\EventEnded::class        => []
        ];
    }

    /**
     * Initialize the model.
     *
     * @param mixed $model
     *
     * @return void
     */
    protected function initialize(mixed $model): void
    {
        if (method_exists($this, 'initModel')) $this->initModel();
        if (!isset($this->__entity)) throw new Exception('The entity variable has not been set.');
        if (!is_object($model)) {
            $model = $this->refind($model_id = $model);
            if (!$model) throw new ModelCouldNotBeIdentifiedByid($model_id);
        }

        if ($this->initialized && static::$__model->getKey() === $model->getKey()) return;

        if ($this->initialized) $this->end();

        event(new Events\InitializingEvent($this));

        $model_name = Str::lower($this->__entity);
        $this->{$model_name} = $model;
        if (isset($this->{$model_name})) $this->__initialize_model = &$this->{$model_name};

        event(new Events\EventInitialized($this));
    }

    /**
     * End the initialization, set the initialized to false and unset the initialize model.
     *
     * @return void
     */
    public function end(): void
    {
        event(new Events\EndingEvent($this));
        if (!$this->initialized) return;
        event(new Events\EventEnded($this));

        $this->initialized = false;
        $this->__initialize_model = null;
    }

    public function getInitializeModel(?string $model = null): ?Model
    {
        if (isset($model)) {
            $model_name = Str::snake(class_basename($model));
            $this->{$model_name} = $model;
        }
        return $this->__initialize_model = &$this->{$model_name};
    }
}
