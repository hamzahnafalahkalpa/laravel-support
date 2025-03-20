<?php

namespace Zahzah\LaravelSupport\Concerns\Support;

trait HasCallStatic
{
    use HasClassModifier;
    use HasCall;

    protected static $__instance;

    /**
     * Handle static method calls.
     *
     * @param  string  $method
     * @param  array  $arguments
     * @return mixed
     */
    public static function __callStatic($method, $arguments=[]){        
        self::$__instance = new static();
        // If the method starts with "use", it will attempt to use the class in the same namespace as the current class.
        if (strpos($method, 'use') === 0) {
            $reflection = new \ReflectionClass(static::class);
            $namespace  = $reflection->getNamespaceName();
            $class_name = $namespace . '\\Schemas\\' . ucfirst(substr($method, 3));
            if (class_exists($class_name)) {
                return self::$__instance->use($class_name, $arguments);
            }
        }

        // If the method doesn't exist on the instance, try to call it dynamically
        if (method_exists(self::$__instance, $method)) {
            return self::$__instance->__call($method, $arguments);
        }

        return self::$__instance->$method(...$arguments);
    }

    /**
     * Use another class.
     *
     * @param  string  $class
     * @return mixed
     */
    public function use($class, ...$arguments){
        if (!class_exists($class)) {
            throw new \InvalidArgumentException(sprintf('Class [%s] does not exist.', $class));
        }
        $this->setClass(new $class(...$arguments));
        return $this->getClass();
    }
}


