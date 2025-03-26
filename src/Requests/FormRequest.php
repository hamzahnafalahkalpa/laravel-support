<?php

namespace Hanafalah\LaravelSupport\Requests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest as Request;
use Illuminate\Validation\Rule;
use Hanafalah\LaravelSupport\Concerns\DatabaseConfiguration\HasModelConfiguration;
use Illuminate\Support\Str;

class FormRequest extends Request
{
    use HasModelConfiguration;

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

    protected function idValidation($model)
    {
        $model = $this->configModel($model);
        return Rule::exists($this->connectionTable($model), $model->getKeyName());
    }

    protected function existsValidation($model,string $key)
    {
        $model = $this->configModel($model);
        return Rule::exists($this->connectionTable($model), $key);
    }

    protected function uniqueValidation($model, ...$args)
    {
        $model = $this->configModel($model);
        return Rule::unique($this->connectionTable($model), $model->getKeyName(), ...$args);
    }

    public function setRulesUUID(array $rules): array
    {
        $model = $this->getModel();
        $uuid  = $model::getUuidName();
        if (isset($rules[$uuid])) {
            $rules[$uuid] = array_merge([Rule::exists($this->connectionTable($model), $uuid)], $rules[$uuid]);
        }
        return $rules;
    }
}
