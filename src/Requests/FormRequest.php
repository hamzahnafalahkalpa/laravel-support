<?php

namespace Hanafalah\LaravelSupport\Requests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest as Request;
use Illuminate\Validation\Rule;
use Hanafalah\LaravelSupport\Concerns\DatabaseConfiguration\HasModelConfiguration;
use Hanafalah\LaravelSupport\Concerns\Support\HasRequestData;
use Hanafalah\ModuleUser\Models\User\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class FormRequest extends Request
{
    use HasModelConfiguration, HasRequestData;

    public $global_user;
    public $global_employee;
    public $global_user_reference;
    public $global_workspace;

    public function gate(?string $type = null){
        $type ??= $this->getRequestName();
    }

    protected function validationRules()
    {
        return $this->dbValidation();
    }
    
    private function dbValidation(){
        if (config('micro-tenant') !== null) {
            config(['micro-tenant.use-db-name' => false]);
            $validation = parent::validationRules();
            config(['micro-tenant.use-db-name' => true]);
            return $validation;
        }else{
            return parent::validationRules();
        }
    }

    public function userAttempt(){
        $user = Auth::user();
        $this->global_user = $user;
        if (isset($user)){
            $user->load([
                'userReference' => function($query){
                    $query->with('role')->where('reference_type', config('module-user.reference'));
                }
            ]);
            $user_reference = $user->userReference;
            $this->global_user_reference = &$user_reference;
            
            if (isset($user_reference) && $user_reference->reference_type == 'Employee'){
                $this->global_employee = $user_reference->reference;
            }
        }
    }

    private function getRequestName(): string{
        $class = class_basename($this);
        switch (true) {
            case Str::startsWith($class, 'View'):
                return 'index';
            break;
            case Str::startsWith($class, 'Store'):
                return 'store';
            break;
            case Str::startsWith($class, 'Update'):
                return 'update';
            break;
            case Str::startsWith($class, 'Delete'):
                return 'destroy';
            break;
        }
    }

    public function callCustomMethod(): array
    {
        return ['Model'];
    }

    public function __construct()
    {
        parent::__construct();
        if (request()->route()) {
            $parameters = request()->route()->parameters();
            request()->merge($parameters);
        }
        if (isset($this->__entity)) {
            static::$__model = $this->configModel($this->__entity);
        }
        $request = request();
        $attributes = $this->requestResolver($request->all());
        request()->merge($attributes);
    }

    protected function digit(){
        return 'regex:/^\d+$/';
    }

    protected function decimal($length){
        return 'regex:/^(\d+(\.\d{1,' . $length . '})?|(\.\d{1,' . $length . '})?)$/';
    }

    private function requestResolver($attributes): array
    {
        foreach ($attributes as $key => &$attribute) {
            if (is_array($attribute)) {
                $attribute = $this->requestResolver($attribute);
            } else {
                if ($attribute === 'null') $attributes[$key] = null;
                if ($attribute === 'undefined') $attributes[$key] = null;
            }
        }
        return $attributes;
    }

    public function usingEntity(?string $model = null): Model{
        return $this->configModel($model ?? $this->__entity);
    }

    private function configModel(string|object $model): Model
    {
        if (is_object($model)) return $model;
        return app(config('database.models.' . $model));
    }

    public function setRules(array $rules): array
    {
        $model = $this->getModel();
        $id    = $model->getKeyName();
        if (isset($rules[$id])) {
            $rules[$id] = array_merge([$this->idValidation($model)], $rules[$id]);
        }
        return $rules;
    }

    private function connectionTable($model){
        return $model->getConnectionName() . '.' . Str::after($model->getTable(), '.');
    }

    protected function inCasesValidation($cases){
        return Rule::in(...array_column($cases, 'value'));
    }

    protected function uuidValidation($model, string $key = 'uuid'){
        $model = $this->configModel($model);
        return Rule::exists($this->connectionTable($model), $key);
    }

    protected function idValidation($model){
        $model = $this->configModel($model);
        return Rule::exists($this->connectionTable($model), $model->getKeyName());
    }

    protected function existsValidation($model,string $key){
        $model = $this->configModel($model);
        return Rule::exists($this->connectionTable($model), $key);
    }

    protected function uniqueValidation($model, ...$args){
        $model = $this->configModel($model);
        return Rule::unique($model->getTableName(), ...$args);
    }

    public function setRulesUUID(array $rules): array{
        $model = $this->getModel();
        $uuid  = $model::getUuidName();
        if (isset($rules[$uuid])) {
            $rules[$uuid] = array_merge([Rule::exists($this->connectionTable($model), $uuid)], $rules[$uuid]);
        }
        return $rules;
    }
}
