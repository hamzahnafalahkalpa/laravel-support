<?php

namespace Zahzah\LaravelSupport\Concerns\PackageManagement;

trait AttributeModifier{

    /** @var bool */
    protected static bool $__with_props  = true;
    protected static string $__prop_column = 'props';
    protected array $__attributes = [];    
    protected array $__guard = [];
    protected array $__add = [];
    
    /**
     * Filter the given attributes with the given add and guard keys.
     *
     * @param array $attributes The attributes to be filtered.
     * @param array $add The keys to be added.
     * @param array $guard The keys to be guarded.
     * @return array The filtered attributes.
     */
    public function outsideFilter(array $attributes, array ...$data): array{
        $result = $attributes;
        foreach ($data as $filters) {
            $result = array_filter($result, function($value, $key) use ($filters) {
                return !in_array($key, $filters) && $value !== null;
            }, ARRAY_FILTER_USE_BOTH);
        }
        return $result;
    }

    public function attributeHasParent(?array $attributes = null): bool{
        $attributes ??= $this->__attributes;
        return (isset($attributes['parent']) && !empty($attributes['parent']));
    }

    /**
     * Calculates the difference of the keys of two arrays.
     *
     * @param array $adds The first array to compare keys.
     * @param array $guards The second array to compare keys.
     * @return array The array containing the keys from the first array that are not present in the second array.
     */
    protected function createInit(? array $attributes,? array $adds = null,? array $guards = null): array {
        $attributes ??= $this->__attributes;
        $adds       ??= $this->__add;
        $guards     ??= $this->__guard;
        $adds = $this->mergeArray(
            $this->mustArray($adds),
            $this->mustArray($guards)
        );
        $adds   = $this->intersectKey($attributes,$adds);
        $guards = $this->intersectKey($adds,$guards);
        $adds   = $this->diffKey($adds, $guards);
        if (static::$__with_props){
            $adds = $this->mergeArray($attributes[static::$__prop_column] ?? [],$adds);
        }
        return (count($guards) > 0) ? [$guards,$adds] : [$adds];
    }

    protected function getAdd(): array{
        return $this->__add;
    }

    protected function getGuard(): array{
        return $this->__guard;
    }

    protected function getAttributes(): array{
        return $this->__attributes;
    }
}