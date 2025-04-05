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
  public function requestDTO(string $dto, ?array $attributes = null, string|array|null $excludes = null): mixed{
    $excludes = $excludes ?? 'props';
    if (!is_array($excludes)) $excludes = [$excludes];
    $attributes ??= request()->all();
    return $this->mapToDTO($dto, $attributes, $excludes);
  }

  private function mapToDTO(string $dto, mixed $attributes = null, ?array $excludes = []): ?Data{    
    if (!isset($attributes)) return null;
    if (Str::contains($dto,'\\Contracts\\')){
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
    $class = new ReflectionClass($dto);
    
    $constructor = $class->getConstructor();
    $parameters  = $constructor->getParameters();
    $parameterDetails = array_map(function ($param) use ($excludes) {
        return $this->DTOChecking($param, $excludes);
    }, array_filter(
        $parameters,
        fn($param) => !in_array($param->getName(), $excludes)
    ));

    $validAttributes = [];
    $props = array_diff_key($attributes, array_flip(array_column($parameterDetails, 'name')));

    foreach ($parameterDetails as $paramDetail) {
      $validAttributes[$paramDetail['name']] = $this->DTOParamChecking($paramDetail, $attributes, $excludes);        
    }
    $validAttributes['props'] = $props;

    $prop = end($parameters);
    if ($prop->name == 'props'){
      $paramDetail = $this->DTOChecking($prop);
      $validAttributes['props'] = $this->DTOParamChecking($paramDetail, $validAttributes, []);
    }

    return $dto::from($validAttributes);
  }

  private function DTOParamChecking(array $paramDetail, array $attributes, ?array $excludes = []){
    $name     = $paramDetail['name'];
    $typeName = $paramDetail['typeName'];
    $isDTO    = $paramDetail['isDTO'];
    
    if (array_key_exists($name, $attributes)) {
      if ($isDTO) {        
        if (array_is_list($attributes[$name])){
          foreach ($attributes[$name] as &$attribute_name) {          
            $attribute_name = $this->mapToDTO($typeName, $attribute_name, $excludes);
          }
        }else{
          $attributes[$name] = $this->mapToDTO($typeName, $attributes[$name], $excludes);
        }
        return $attributes[$name];
      } else {
        return $attributes[$name];
      }
    }
  }

  private function DTOChecking($param,array $excludes = []): array{
    $name = $param->getName();
    $type = $param->getType();
    $typeName = null;
    $isDTO = false;
    if (method_exists($type, 'getTypes')) {
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
