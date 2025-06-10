<?php

namespace Hanafalah\LaravelSupport\Concerns\PackageManagement;

trait AttributeModifier
{

    /** @var bool */
    // protected static bool $__with_props  = true;
    // protected static string $__prop_column = 'props';
    protected array $__attributes = [];

    // /**
    //  * Filter the given attributes with the given add and guard keys.
    //  *
    //  * @param array $attributes The attributes to be filtered.
    //  * @param array $add The keys to be added.
    //  * @param array $guard The keys to be guarded.
    //  * @return array The filtered attributes.
    //  */
    // public function outsideFilter(array $attributes, array ...$data): array
    // {
    //     $result = $attributes;
    //     foreach ($data as $filters) {
    //         $result = array_filter($result, function ($value, $key) use ($filters) {
    //             return !in_array($key, $filters) && $value !== null;
    //         }, ARRAY_FILTER_USE_BOTH);
    //     }
    //     return $result;
    // }

    // public function attributeHasParent(?array $attributes = null): bool
    // {
    //     $attributes ??= $this->__attributes;
    //     return (isset($attributes['parent']) && !empty($attributes['parent']));
    // }

}
