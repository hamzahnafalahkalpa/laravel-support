<?php

namespace Zahzah\LaravelSupport\Concerns\Support;

trait HasUserInfo{

    protected static array $user_info = [];

    /**
     * Get the user information.
     *
     * @return array
     */
    public function getUserInfo(){
        return static::$user_info;
    }

    /**
     * Merge the user information with the given value.
     *
     * @param mixed $value
     * 
     * @return self
     */
    public function mergeUserInfo(mixed $value): self{
        $value = $this->mustArray($value);
        static::$user_info = $this->mergeArray(static::$user_info, $value);
        return $this;
    }

    /**
     * Set the user information with the given value.
     *
     * @param mixed $value
     * 
     * @return self
     */
    public function setUserInfo(mixed $value): self{
        $value = $this->mustArray($value);
        static::$user_info = array_merge($value);
        return $this;
    }

    /**
     * Set a user attribute with the given key and value.
     *
     * @param string $key
     * @param mixed $value
     * 
     * @return self
     */
    public function setUserAttribute($key, $value): self{
        static::$user_info[$key] = $value;
        return $this;
    }
}