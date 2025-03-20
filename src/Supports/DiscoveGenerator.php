<?php

namespace Zahzah\LaravelSupport\Supports;

use Exception;
use Illuminate\Database\Eloquent\Collection;
use ReflectionClass;

class DiscoverGenerator
{
    public static $resolveCallback;

    public static function create(): self
    {
        return new self();
    }

    public static function resolveUsing(callable $resolveCallback)
    {
        self::$resolveCallback = $resolveCallback;
    }

    public function generate(Collection $models): array
    {
        $usedMorphs = [];

        return $models
            ->mapWithKeys(fn (ReflectionClass $reflection) => $this->resolveMorphFromClass($reflection))
            ->reject(fn (string $morph) => class_exists($morph))
            ->mapWithKeys(function (string $morph, string $modelClass) use (&$usedMorphs) {
                // if (array_key_exists($morph, $usedMorphs)) {
                //     throw DuplicateMorphClassFound::create($modelClass, $usedMorphs[$morph]);
                // }

                $usedMorphs[$morph] = $modelClass;

                return [$morph => $modelClass];
            })->toArray();
    }

    private function resolveMorphFromClass(ReflectionClass $reflection): ?array
    {
        /** @var \Illuminate\Database\Eloquent\Model $model */
        $model = $reflection->newInstanceWithoutConstructor();

        // try {
            if (self::$resolveCallback) {
                $morph = call_user_func(self::$resolveCallback, $model);
            }

            // $morph ??= $model->getMorphClass();
        // } catch (Exception $exception) {
        //     throw MorphClassCouldNotBeResolved::exceptionThrown($reflection->getName(), $exception);
        // }

        // if (empty($morph)) {
        //     throw MorphClassCouldNotBeResolved::nullReturned($reflection->getName());
        // }

        return [$reflection->getName() => $morph];
    }
}
