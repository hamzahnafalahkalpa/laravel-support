<?php

namespace Zahzah\LaravelSupport\Concerns\PackageManagement;

use Zahzah\LaravelSupport\{
    Concerns\Support,
    Concerns\DatabaseConfiguration,
    Concerns\ServiceProvider
};
use Zahzah\LaravelSupport\Concerns\Support\RequestManipulation;

trait DataManagement{
    use RequestManipulation;
    use AttributeModifier;
    use Support\HasRepository;
    use DatabaseConfiguration\HasModelConfiguration;
    use ServiceProvider\HasConfiguration;
    use ORMImpersonate;

    protected array $__props = [];
    protected array $__morphs = [];

    /**
     * Adds the given attributes to the class with looping the given attributes.
     *
     * @param array $attributes The attributes to be added.
     * @return self Returns the SetupManagement instance.
     */
    public function adds(? array $attributes=null,array $parent_id=[]): self{
        $attributes ??= request()->all();
        foreach ($attributes as $key => $attribute) {
            $attribute = $this->mergeArray($attribute, $parent_id);
            $this->booting()->add($attribute);
        }
        return $this;
    }
    
    /**
     * Adds the given attributes to the class.
     *
     * @param array $attributes The attributes to be added.
     * @return void
     */
    public function add(? array $attributes = null): self{
        $attributes ??= request()->all();
        if ($this->isSetSchemaThrow()){
            if ($this->isMethodExists('addOrChange'))
                static::addOrChange($attributes);
        }
        return $this;
    }

    public function inheritenceLoad(object &$model,string $relation,?callable $callback = null): void{
        if (isset($callback)){
            $relation_load = [
                $relation => function($query) use ($callback){
                    $callback($query);
                }
            ];
        }else{
            $relation_load = $relation;
        }
        $model->load($relation_load);

        if (isset($model->{$relation}) && count($model->{$relation}) > 0){
            foreach ($model->{$relation} as &$relation_model) {
                if (isset($callback)){
                    $this->inheritenceLoad($relation_model,$relation,function($query) use ($callback){
                        $callback($query);
                    });
                }else{
                    $this->inheritenceLoad($relation_model,$relation);
                }
            }
        }
    }

    /**
     * A description of the entire PHP function.
     *
     * @param datatype $paramname description
     * @throws Some_Exception_Class description of exception
     * @return Some_Return_Value
     */
    public function change(? array $attributes = null): self{
        $attributes ??= request()->all();        
        if ($this->isMethodExists('addOrChange'))
            static::addOrChange($attributes);
            // : static::$__class->change($attributes);
        return $this;
    }

    public function mergeProps(array $attributes = []): array{
        if (count($this->__props) > 0){
            $prop_fields = [];
            foreach ($this->__props as $key => $field) {
                if (!is_numeric($key)){
                    $prop_fields[$key] = $field;
                }else{
                    if (array_key_exists($field, $attributes)) {
                        $prop_fields[$field] = $attributes[$field] ?? null;
                    }
                }
            }
            $attributes[static::$__prop_column] = $this->mergeArray($attributes[static::$__prop_column] ?? [],$prop_fields);
        }
        return $attributes;
    }

    public function toProps(array $fields = []): self{
        $this->__props = $fields;
        return $this;
    }

    public function setAdd(string|array $attributes,bool $overwrite = false) : self {
        $add = (!$overwrite) ? $this->__add : [];
        $this->__add = $this->mergeArray($this->mustArray($attributes),$add);
        return $this;
    }

    public function setGuard(string|array $attributes,bool $overwrite = false) : self {
        $guard = (!$overwrite) ? $this->__guard : [];
        $this->__guard = $this->mergeArray($this->mustArray($attributes),$guard);
        return $this;
    }


    /**
     * Add the given attributes to the class with the given add and guard keys.
     *
     * @param array $attributes The attributes to be added.
     * @param array $add The keys to be added.
     * @param array $guard The keys to be guarded.
     * @return self Returns the SetupManagement instance.
     */
    protected function beforeResolve(array $attributes,? array $add = null,? array $guard = null): self{

        $attributes    ??= $this->__attributes; 
        $add           ??= $this->__add; 
        $guard         ??= $this->__guard; 
        if (isset($attributes['parent_model'])) unset($attributes['parent_model'],$attributes['parent']);
        $attributes      = $this->outsideFilter($attributes,$add,$guard,$attributes['props'] ?? []);
        
        $parent_id       = [static::$__model->getForeignKey() => static::$__model->getKey()];
        $self_parent_id  = ['parent_id' => static::$__model->getKey()];
        foreach ($attributes as $key => $attribute) {
            $this->fork(function() use ($key,$attribute,$self_parent_id,$parent_id){
                if ($this->inArray($key,['childs','child','parent'])){
                    // add the parent id to the attribute and add it to the child table

                    ($key == 'childs')
                        ? $this->booting()->adds($attribute,$self_parent_id)
                        : $this->booting()->add($this->mergeArray($attribute,$self_parent_id));
                }else{
                    // if the attribute is a method of the current class, call it with the given value
                    if ($this->hasMethod($this, $key)) {
                        $attribute = ($this->isCallable($attribute)) ? $attribute() : $attribute;
                        $this->{$key}($attribute);
                    }else{
                        if (method_exists($this,'morphs')){
                            $key = $this->morphs($key) ?? $key;                            
                        }
                        $class_namespace = $key;                        
                        
                        if (\class_exists($class_namespace)){
                            // if the attribute is a registered service, call the service with the given value
                            $parent_model = self::$__model;
                            
                            $this->childSchema($class_namespace,function($class,$parent) use ($parent_id,$parent_model,$attribute){
                                // call the service with the given value and add the result to the current schema
                                if (array_is_list($attribute)){
                                    foreach ($attribute as $attr) {
                                        $attr = $this->joinWithParent($attr,$parent_id,$parent_model,$parent);
                                        $class->booting()->add($attr);
                                    }
                                }else{
                                    $attr = $this->joinWithParent($attribute,$parent_id,$parent_model,$parent);
                                    $class->add($attr);
                                }
                            });
                        }
                    }
                }
            });
        }
        return $this;
    }    

    private function joinWithParent($attr,$parent_id,$parent_model,$parent){
        $attr = $this->mergeArray($parent_id,[
            'parent' => $parent,
            'parent_model' => $parent_model
        ],$attr);
        return $attr;
    }


    public function morphs(string $key = null): null|string|array{
        if (!isset($key)) return $this->__morphs;
        return $this->__morphs[$key] ?? null;
    }

    public function paginateOptions(){
        $paginate_options = compact('perPage', 'columns', 'pageName', 'page', 'total');
        return $this->arrayValues($paginate_options);
    }
    
    protected function escapingVariables(callable $callback,...$args): void{
        $model       = static::$__model;
        $localConfig = $this->__local_config;
        $class       = static::$__class;
        $attributes  = $this->__attributes;
        $entity      = $this->__entity;
        $this->requestScope(function() use ($callback,$args){
            $callback(...$args);
        });
        static::$__model = $model;
        $this->__local_config = $localConfig;
        static::$__class = $class;
        $this->__attributes = $attributes;
        $this->__entity = $entity;

    }
}