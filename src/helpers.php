<?php

use Illuminate\Support\Str;

if (! function_exists('class_name_builder')) {

    function class_name_builder($name) {
        return preg_replace('/[^a-zA-Z0-9_]/', '', Str::replace('.','_',Str::ucfirst(Str::camel($name))));
    }
}

if (! function_exists('namespace_builder')) {
    /**
     * Convert a given string into namespace format
     * e.g. `Laravel Support` to `laravel_support`
     *
     * @param  string  $name
     * @param  string  $delimiter
     * @return string
     */
    function namespace_builder($name,$delimiter="_") {
        return Str::snake($name,$delimiter);
    }
}
