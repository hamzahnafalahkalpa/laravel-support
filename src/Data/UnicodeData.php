<?php

namespace Hanafalah\LaravelSupport\Data;

use Hanafalah\LaravelSupport\Concerns\Support\HasRequestData;
use Hanafalah\LaravelSupport\Supports\Data;
use Hanafalah\LaravelSupport\Contracts\Data\UnicodeData as DataUnicodeData;
use Hanafalah\ModuleService\Contracts\Data\ServiceData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapName;

class UnicodeData extends Data implements DataUnicodeData{
    use HasRequestData;

    #[MapInputName('id')]
    #[MapName('id')]
    public mixed $id = null;
    
    #[MapInputName('parent_id')]
    #[MapName('parent_id')]
    public mixed $parent_id = null;

    #[MapInputName('name')]
    #[MapName('name')]
    public ?string $name = null;
    
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

    #[MapInputName('reference_type')]
    #[MapName('reference_type')]
    public ?string $reference_type = null;

    #[MapInputName('reference_id')]
    #[MapName('reference_id')]
    public mixed $reference_id = null;

    #[MapInputName('childs')]
    #[MapName('childs')]
    public array $childs = [];

    #[MapInputName('props')]
    #[MapName('props')]
    public ?array $props = [];


    public static function before(array &$attributes){
        $new = static::new();
        if (isset($attributes['childs'])){
            foreach ($attributes['childs'] as &$child) {
                $child['flag'] ??= $attributes['flag'];
                $child['label'] ??= $attributes['label'] ?? null;
                // self::before($child);
                $dto = config('app.contracts.'.$child['flag'].'Data');
                if (!isset($dto)) $dto = config('app.contracts.UnicodeData');
                $child = $new->requestDTO($dto, $child);
            }
        }
    }
}
