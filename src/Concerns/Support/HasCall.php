<?php

namespace Hanafalah\LaravelSupport\Concerns\Support;

use Illuminate\Support\Str;
use Hanafalah\LaravelSupport\Supports\PackageManagement;

trait HasCall
{
    private $__call_method, $__call_arguments;
    protected $__custom_method_lists = [];
    /**
     * Dynamically handle calls to the class.
     *
     * @param string $method
     * @param array  $arguments
     *
     * @return mixed
     */
    public function __call($method, $arguments = [])
    {
        return $this->setCallMethod($method)
            ->setCallArguments($arguments)
            ->callMethod();
    }

    /**
     * Set the call method.
     *
     * @param string $method
     *
     * @return self
     */
    private function setCallMethod(string $method): self
    {
        $this->__call_method = $method;
        return $this;
    }

    /**
     * Set the call arguments.
     *
     * @param array $arguments
     *
     * @return self
     */
    private function setCallArguments($arguments): self
    {
        $this->__call_arguments = $arguments;
        return $this;
    }

    /**
     * Call the method dynamically.
     *
     * This method will iterate over the list of methods and call them in order.
     * If the method returns a value, it will be returned.
     *
     * @return mixed|null
     */
    private function callMethod()
    {
        $fn = [
            'callGetStaticResultMethod',
            'callSetStaticResultMethod',
            'callGetResultMethod',
            'callSetResultMethod',
            'renderCallCustomMethod',
            'callParentMethod',
            'callThisMethod'
        ];
        try {
            foreach ($fn as $func) {
                $result = $this->{$func}();
                if (isset($result)) {
                    return $result;
                }
            }
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    /**
     * Check if the call method is a getter method that starts with 'getStatic' ends with 'Result'
     * and return the value of the property if it exists.
     *
     * @return mixed|null
     */
    private function callGetStaticResultMethod()
    {
        if (Str::startsWith($this->__call_method, 'getStatic') && Str::endsWith($this->__call_method, 'Result')) {
            $property = Str::replaceFirst('getStatic', '', $this->__call_method);
            $property = str_replace('Result', '', $property);
            $var      = static::${'__' . Str::snake($property)};
            if (isset($var)) return $var;
        }
    }

    /**
     * Check if the call method is a getter method that starts with 'get' ends with 'Result'
     * and return the value of the property if it exists.
     *
     * @return mixed|null
     */
    private function callGetResultMethod()
    {
        if (Str::startsWith($this->__call_method, 'get') && Str::endsWith($this->__call_method, 'Result')) {
            $property = Str::replaceFirst('get', '', $this->__call_method);
            $property = str_replace('Result', '', $property);
            $var      = $this->{'__' . Str::snake($property)};
            if (isset($var)) return $var;
        }
    }

    /**
     * Check if the call method is a getter method that starts with 'getStatic' ends with 'Result'
     * and return the value of the property if it exists.
     *
     * @return mixed|null
     */
    private function callSetStaticResultMethod()
    {
        if (Str::startsWith($this->__call_method, 'setStatic') && Str::endsWith($this->__call_method, 'Result')) {
            $property = Str::replaceFirst('setStatic', '', $this->__call_method);
            $property = str_replace('Result', '', $property);
            if (isset(static::${'__' . Str::snake($property)})) {
                return static::${'__' . Str::snake($property)} = $this->__call_arguments[0];
            }
        }
    }

    /**
     * Check if the call method is a setter method that starts with 'set' ends with 'Result'
     * and return the value of the property if it exists.
     *
     * @return mixed|null
     */
    private function callSetResultMethod()
    {
        if (Str::startsWith($this->__call_method, 'set') && Str::endsWith($this->__call_method, 'Result')) {
            $property = Str::replaceFirst('set', '', $this->__call_method);
            $property = str_replace('Result', '', $property);
            if (isset($this->{'__' . Str::snake($property)})) {
                return $this->{'__' . Str::snake($property)} = $this->__call_arguments[0] ?? null;
            }
        }
    }

    protected function setCustomMethodList(array $methods = []): self
    {
        $this->__custom_method_lists = $methods;
        return $this;
    }

    protected function renderCallCustomMethod()
    {
        if (\method_exists($this, 'callCustomMethod')) {
            $var = $this->bootCustomMethod($this->callCustomMethod());
            if (isset($var)) return $var;
        }
    }

    protected function bootCustomMethod(mixed $methods = [])
    {
        $methods = $this->mustArray($methods);
        foreach ($methods as $method) {
            $method = '__call' . $method;
            if (!method_exists($this, $method)) {
                throw new \Exception('Method ' . $method . ' does not exist in ' . $this);
            }
            $var = $this->{$method}();
            if (isset($var)) return $var;
        }
    }

    private function callParentMethod()
    {
        try {
            if (is_string(get_parent_class($this)) && method_exists(parent::class, '__call')) {
                return parent::__call($this->__call_method, $this->__call_arguments);
            }
        } catch (\Throwable $th) {
            if ($th->getMessage() != 'Cannot use "parent" when current class scope has no parent') {
                throw $th;
            }
        }
    }

    /**
     * Calls the current instance method if it exists.
     *
     * @return mixed|null
     */
    private function callThisMethod()
    {
        if (method_exists($this, $this->__call_method)) {
            return $this->{$this->__call_method}(...$this->__call_arguments);
        }
    }

    protected function getCallMethod()
    {
        return $this->__call_method;
    }

    protected function getCallArguments()
    {
        return $this->__call_arguments;
    }
}
