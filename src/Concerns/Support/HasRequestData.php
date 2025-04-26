<?php

namespace Hanafalah\LaravelSupport\Concerns\Support;

use Closure;
use Hanafalah\LaravelSupport\Supports\Data;
use ReflectionClass;
use Illuminate\Support\Str;
use ReflectionFunction;
use Spatie\LaravelData\Attributes\DataCollectionOf;


trait HasRequestData
{
    protected $__dto;

    public function requestDTO(object|string $dto, ?array $attributes = null, string|array|null $excludes = null): mixed{
        $excludes = $excludes ?? 'props';
        if (!is_array($excludes)) $excludes = [$excludes];
        $attributes ??= request()->all();
        return $this->mapToDTO($dto, $attributes, $excludes);
    }

    private function mapToDTO(object|string $dto, mixed $attributes = null, ?array $excludes = []): ?Data{    
        if (!isset($attributes)) return null;
        $class            = $this->resolvedClass($dto);
        $parameters       = $this->getParameters($class);
        $this->__dto = $dto;
        $parameterDetails = $this->getParameterDetails($parameters,$excludes);        
        
        $validAttributes = [];
        $props = array_diff_key($attributes, array_flip(array_column($parameterDetails, 'name')));
        foreach ($parameterDetails as $paramDetail) {
            $validAttributes[$paramDetail['name']] = $this->DTOParamChecking($paramDetail, $attributes, $excludes);        
        }
        
        $this->processProperties($validAttributes, $parameters, $props);
        try {
            $data = $dto::from($validAttributes);
        } catch (\Throwable $th) {
            throw $th;
        }
        if (method_exists($dto, 'after') && (new \ReflectionMethod($dto, 'after'))->isStatic()) {
            $data = $dto::after($data);
        }

        return $data;
    }

    private function processProperties(array &$validAttributes, array $parameters, ?array $props = null){
        $prop = null;
        $prop_exists = false;
        foreach($parameters as $param){
            if($param->getName() == 'props'){
                $prop_exists = true;
                $prop = $param;
                break;
            }
        }
        if ($prop_exists){
            $validAttributes['props'] = $props;

            if (isset($prop->name) && $prop->name == 'props'){
                $paramDetail = $this->DTOChecking($prop);
                $validAttributes['props'] = $this->DTOParamChecking($paramDetail, $validAttributes, []);
            }
        }

    }

    private function getParameterDetails(array $parameters,?array $excludes = []){
        return array_values(array_map(function ($param) use ($excludes) {
            return $this->DTOChecking($param, $excludes);
        }, array_filter(
            $parameters,
            fn($param) => !in_array($param->getName(), $excludes) && !Str::contains($param->getName(), '__')
        )));
    }

    private function resolvedClass(mixed &$dto){
        if (is_string($dto) && Str::contains($dto,'\\Contracts\\')){
            $binding = app()->getBindings()[$dto];

            $concrete = $binding['concrete'];
            if ($concrete instanceof Closure) {
                $parameters    = (new ReflectionFunction($concrete))->getStaticVariables();
                $resolvedClass = $parameters['bind'] ?? null;
            } else {
                $resolvedClass = $concrete;
            }

            if (!$resolvedClass) throw new \Exception("Unable to determine the target class for {$dto}");

            $dto = $resolvedClass;
        }
        return new ReflectionClass(is_object($dto) ? $dto::class : $dto);
    }

    private function getParameters($class){
        $constructor = $class->getConstructor();
        if (isset($constructor)) {
            $parameters = $constructor->getParameters();
        }else{
            $parameters = $class->getProperties();
            $parameters = array_filter($parameters, function($param){
                return !Str::startsWith($param->getName(), '_');
            });
            $parameters = \array_values($parameters);
        }
        return $parameters;
    }

    private function DTOParamChecking(array $paramDetail, array $attributes, ?array $excludes = []){
        $name     = $paramDetail['name'];
        $typeName = $paramDetail['typeName'];
        $isDTO    = $paramDetail['isDTO'];
        if (array_key_exists($name, $attributes)) {
            if ($isDTO) {  
                if (isset($attributes[$name])){
                    if (is_array($attributes[$name]) && array_is_list($attributes[$name]) && count($attributes[$name]) > 0){
                        foreach ($attributes[$name] as &$attribute_name) $attribute_name = $this->mapToDTO($typeName, $attribute_name, $excludes);
                    }else{
                        $attributes[$name] = (count($attributes[$name]) == 0) ? [] : $this->mapToDTO($typeName, $attributes[$name], $excludes);
                    }
                }
                return $attributes[$name];
            } else {
                return $attributes[$name];
            }
        }
    }

    private function DTOChecking($param,array $excludes = []): array{
        $name = $param->getName();
        $type = $param->getType() ?? null;
        $typeName = null;
        $isDTO = false;
        if (isset($type) && method_exists($type, 'getTypes')) {
            foreach ($type->getTypes() as $unionType) {
                if (!$unionType->isBuiltin()) {
                    $typeName = $unionType->getName();
                    break;
                }
            }
        } else {
            $typeName = $type->getName();
        }

        // Resolve jika typeName adalah contract
        if ($typeName && Str::contains($typeName, '\\Contracts\\')) {
            $binding = app()->getBindings()[$typeName];
            $concrete = $binding['concrete'];
            
            if ($concrete instanceof Closure) {
                $parameters = (new ReflectionFunction($concrete))->getStaticVariables();
                $resolvedClass = $parameters['bind'] ?? null;
            } else {
                $resolvedClass = $concrete;
            }

            $typeName = $resolvedClass;
        }
        if ($typeName == 'array'){
            $attributes = $param->getAttributes();
            foreach ($attributes as $attribute) {
                if ($attribute->getName() == DataCollectionOf::class){
                    $isDTO = true;
                    foreach ($attribute->getArguments() as $argument) {
                        $typeName = $argument;
                        // $typeName = config('app.contracts.'.Str::afterLast($argument,'\\'));                            
                        break;
                    }
                    break;
                }
            }
        }else{
            $isDTO = $typeName && is_subclass_of($typeName, Data::class);
        }

        return compact('name', 'typeName', 'isDTO');
    }
}
