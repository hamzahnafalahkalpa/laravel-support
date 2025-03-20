<?php

namespace Hanafalah\LaravelSupport\Concerns\Support;

use Composer\Autoload\ClassLoader;

trait HasClassModifier
{
    public static $__class, $__classes = [];
    /**
     * Set the class for the SetupManagement instance.
     *
     * @param string $className The class name to set.
     * @return self Returns the SetupManagement instance.
     */
    protected function setClass(string|object $className): self
    {
        $class = (!is_string($className)) ? $className : app($className);
        static::$__classes[$class::class] = $class;
        static::$__class = &static::$__classes[$class::class];
        // if ($this->hasMethod(static::$__class,'initModel')) {
        //     static::$__class->initModel(true,$className);
        // }
        return $this;
    }

    /**
     * Check if a method exists in the given class.
     *
     * @param  mixed  $class  The class to check.
     * @param  string  $method  The method to check.
     * @return bool  True if the method exists, false otherwise.
     */
    protected function hasMethod($class, $method)
    {
        return \method_exists($class, $method);
    }

    protected function isCallable($callable)
    {
        return \is_callable($callable);
    }

    /**
     * Retrieves the class associated with the current instance.
     *
     * @return mixed The class associated with the current instance.
     */
    public function getClass(): mixed
    {
        return self::$__class;
    }

    /**
     * Retrieves all the classes associated with the current instance.
     *
     * @return array The classes associated with the current instance.
     */
    public function getClasses(): array
    {
        return self::$__classes;
    }

    /**
     * Retrieves the base name of the given class.
     *
     * @param string|object $class The class to retrieve the base name for.
     *
     * @return string The base name of the given class.
     */
    protected function getClassBaseName($class): string
    {
        return class_basename(\is_string($class) ? app($class) : $class);
    }

    /**
     * Retrieves a constant value from a given model.
     *
     * @param mixed $model The model from which to retrieve the constant.
     * @param string $constant The name of the constant to retrieve.
     * @return mixed The value of the constant.
     */
    public function constant($model, $constant): mixed
    {
        return constant(\get_class($model) . '::' . \strtoupper($constant));
    }


    protected function loader($new = false): ClassLoader
    {
        return $this->__loader = (!$new) ? require \base_path() . '/vendor/autoload.php' : new ClassLoader;
    }
}
