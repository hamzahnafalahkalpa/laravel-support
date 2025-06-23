<?php

namespace Hanafalah\LaravelSupport\Data;

use Hanafalah\LaravelSupport\Supports\Data;
use Hanafalah\LaravelSupport\Contracts\Data\UnicodeData as DataUnicodeData;
use Hanafalah\ModuleService\Contracts\Data\ServiceData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapName;

class UnicodeData extends Data implements DataUnicodeData{
    #[MapInputName('id')]
    #[MapName('id')]
    public mixed $id = null;
    
    #[MapInputName('parent_id')]
    #[MapName('parent_id')]
    public mixed $parent_id = null;

    #[MapInputName('name')]
    #[MapName('name')]
    public string $name;
    
    #[MapInputName('flag')]
    #[MapName('flag')]
    public string $flag;

    #[MapInputName('label')]
    #[MapName('label')]
    public ?string $label = null;

    #[MapInputName('ordering')]
    #[MapName('ordering')]
    public ?int $ordering = 1;
    
    #[MapInputName('status')]
    #[MapName('status')]
    public ?string $status = null;
    
    #[MapName('service')]
    #[MapInputName('service')]
    public ?ServiceData $service = null;

    #[MapInputName('childs')]
    #[MapName('childs')]
    #[DataCollectionOf(self::class)]
    public array $childs = [];

    #[MapInputName('props')]
    #[MapName('props')]
    public ?array $props = [];

    public static function before(array &$attributes){
        if (isset($attributes['childs'])){
            foreach ($attributes['childs'] as &$child) {
                $child['flag'] = $attributes['flag'];
                $child['label'] ??= $attributes['label'] ?? null;
                self::before($child);
            }
        }
    }
}
