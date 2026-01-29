<?php
namespace Hanafalah\LaravelSupport\Jobs;

class JobRequest
{
    protected static array $data = [];

    public static function set(array $data): void
    {
        self::$data = $data;
    }

    public static function all(): array
    {
        return self::$data;
    }

    public static function get(string $key, $default = null)
    {
        return self::$data[$key] ?? $default;
    }
}
