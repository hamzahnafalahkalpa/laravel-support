<?php

namespace Hanafalah\LaravelSupport\Concerns\Support;

trait HasJson
{
    /**
     * Parse a json string to php array
     *
     * @param string $json
     * @return mixed
     */
    public function parse(string $json): mixed
    {
        return json_decode($json, true);
    }

    /**
     * Encode a php array to json string
     *
     * @param mixed $json
     * @return string
     */
    public function encode(mixed $json): string
    {
        return json_encode($json, JSON_PRETTY_PRINT);
    }
}
