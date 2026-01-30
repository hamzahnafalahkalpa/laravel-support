<?php

namespace Hanafalah\LaravelSupport\Concerns\Support;

use Illuminate\Database\Eloquent\{
    Casts\Json,
    Model
};
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Hanafalah\ApiHelper\Facades\ApiAccess;
use Hanafalah\LaravelSupport\Facades\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use ReflectionNamedType;
use ReflectionClass;

trait HasResponse
{
    use HasArray, HasCache;

    protected int $__response_code;
    protected array $__response_messages = [];
    protected mixed $__response_result;
    protected array $response;

    public function sendResponse(mixed $result, ?int $code = 200, mixed $message = 'Success.'): \Illuminate\Http\JsonResponse
    {
        $this->responseFormat($result, $code, $message);
        return response()->json($this->resultResponse(), $code);
    }

    public function responseFormat(mixed $result, ?int $code = 200, mixed $message = 'Success.'): self
    {
        $this->setResponseCode($code)->setResponseMessages($message)->setResponseResult($result);
        return $this;
    }

    public function resultResponse(): array
    {
        $success = $this->__response_code < 400;
        if ($success) $this->renderAclResponse();
        $this->__response = array_merge([
            'data' => $this->__response_result,
            'meta' => [
                'code'     => $this->__response_code,
                'success'  => $success,
                'messages' => $this->mustArray($this->__response_messages),
            ],
            'acl' => $this->__response['acl'] ?? null
        ]);
        return $this->__response;
    }

    private function renderAclResponse()
    {
        $route      = request()->route();
        $route_name = $route ? $route->getName() : null;
        if (Auth::check()) {
            $user = $this->prepareUser();
            $role = $user->userReference->role;
            request()->merge([
                'role_id' => isset($role) ? $role->getKey() : null,
                'is_show_in_acl' => true
            ]);
            $permission = app(config('database.models.Permission'));
            if (isset($route_name) && \is_subclass_of($permission, Model::class)) {
                $route_permission = $this->setCache([
                    'name'    => 'permission-route-'.$route_name.'-role-'.request()->role_id,
                    'tags'    => ['permission','permission-route','permission-route-'.$route_name,'role-'.request()->role_id],
                    'duration' => 24*60*60
                ],function() use ($permission, $route_name){
                    return $permission->with(['recursiveModules','childs' => function($query){
                                                    $query->asPermission()->showInAcl()
                                                         ->where(function($query){
                                                            $query->whereNull('props->show_in_data')
                                                                  ->orWhere('props->show_in_data',false);
                                                         })
                                                         ->checkAccess(request()->role_id);
                                                }])
                                               ->where("alias", $route_name)
                                               ->showInAcl()
                                               ->checkAccess(request()->role_id)
                                               ->first();
                });
                if (!isset($route_permission) && Response::getAclPermission() !== null) {
                    $route_permission = Response::getAclPermission();
                }
                if (isset($route_permission)) {
                    $this->recursiveModules($route_permission->recursiveModules);
                    $childs = $route_permission->childs()->asPermission()->showInData()->get();
                    (isset($this->__response_result['data']))
                        ? $data = &$this->__response_result['data']
                        : $data = &$this->__response_result;
                    (array_is_list($data))
                        ? $datas = &$data
                        : $datas = [&$data];
                    [$controllerClass, $methodName, $methodType] = $this->checkingMethod();
                    if (isset($childs) && count($childs) > 0) {
                        foreach ($datas as &$data) {
                            request()->merge(['id' => $data['id'] ?? null]);
                            $data['accessibility'] = [];
                            foreach ($childs as &$child) {
                                $child->access = $this->getCurrentFormRequestInstance($child->alias) ?? true;
                                $data['accessibility'][Str::afterLast($child->alias, '.')] = $child->access;
                            }
                        }
                    }else{
                        if ($methodType !== 'DELETE'){
                            if (array_is_list($datas)) {
                                foreach ($datas as &$data) {
                                    $data['accessibility'] = null;
                                } 
                            }else{
                                $datas['accessibility'] = null;
                            }
                        }
                    }
                }
            }
            $this->__response['acl'] = isset($route_permission) ? $route_permission->toViewApi()->resolve() : null;
        }
    }

    private function recursiveModules(&$permissions){
        foreach ($permissions as &$permission) {
            if ($permission->access){
                $permission->access = $this->getCurrentFormRequestInstance($permission->alias) ?? true;
            }
            //ACCESS GATE HERE
            $permission->load(['childs' => function($query){
                $query->showInAcl()->asPermission();
            }]);
            if (isset($permission->recursiveModules) && count($permission->recursiveModules) > 0) {
                $this->recursiveModules($permission->recursiveModules);
            }
        }
    }

    private function checkingMethod(?string $alias = null){
        $route = isset($alias) ? Route::getRoutes()->getByName($alias) : Route::getCurrentRoute();
        if (!$route) return null;

        $action = $route->getActionName(); 
        if (!str_contains($action, '@')) return null;
        return [...explode('@', $action),...$route->methods()];

    }
    private function getCurrentFormRequestInstance(?string $alias = null): mixed
    {
        [$controllerClass, $methodName, $methodType] = $this->checkingMethod($alias);
        if (!class_exists($controllerClass)) return null;
        
        $reflection = new ReflectionClass($controllerClass);
        if (!$reflection->hasMethod($methodName)) return null;

        $method = $reflection->getMethod($methodName);
        $params = $method->getParameters();
        foreach ($params as $param) {
            $type = $param->getType();
            if ($type instanceof ReflectionNamedType && is_subclass_of($type->getName(), FormRequest::class)) {
                try {
                    app($type->getName());
                    return true;
                } catch (\Throwable $th) {
                    return false;
                }
            }
        }
        return false;
    }

    private function prepareUser()
    {
        $user           = $this->UserModel()->find(auth()->user()->getKey());
        $user_reference = &$user->userReference;
        $user_reference->setRelation('role', $user_reference->role);
        return $user;
    }

    /**
     * Transforms the current model using the specified resource and callback.
     *
     * @param string|null $resource The resource class to be used for transformation. Defaults to the class's resource property.
     * @param callable|null $callback An optional callback function to modify the model before transformation.
     */
    public function transforming(?string $resource = null, mixed $callback = null, array $options = []): array|Model
    {
        if (isset($callback)) $model = (is_callable($callback)) ? $callback() : (object) $callback;

        $this->resource($resource ??= $this->__resource);
        if ($this->withResource()) {
            $this->setModel($model);
            return $this->retransform($model, function ($model) {
                return new $this->__resource($model);
            }, $options);
        }
        return $model;
    }

    public function withResource(): bool
    {
        return isset($this->__resource);
    }

    public function isSearch(): bool
    {
        $keys  = $this->keys(request()->all());
        $keys  = preg_grep('/^search_/', $keys);
        $valid = count($keys) == 0 || \call_user_func(function () use ($keys) {
            $valid = true;
            foreach ($keys as $key) {
                if (isset(request()->{$key})) {
                    $valid = false;
                    break;
                }
            }
            return $valid;
        });
        return $valid || isset(request()->page);
    }

    public function resource(?string $resource = null): self
    {
        $this->__resource = $resource;
        return $this;
    }


    /**
     * Transform the collection using the given callback, and return the transformed value.
     * If the collection is a LengthAwarePaginator, it will be transformed in place.
     * If the collection is a Model, it will be passed to the callback directly.
     * If the collection is a Collection, it will be transformed in place.
     *
     * @param mixed $collections The collection to transform.
     * @param callable $callback The callback to use for transformation.
     * @return mixed The transformed value.
     */
    public function retransform(mixed $collections, callable $callback, array $options = []): mixed
    {
        switch (true) {
            case $collections instanceof LengthAwarePaginator:
            case $collections instanceof SupportCollection:
            case $collections instanceof Collection:
                $collections->transform(function ($collection) use ($callback) {
                    return $callback($collection);
                });
            break;
            case \is_object($collections):
            case $collections instanceof Model:
                $collections = $callback($collections);
                break;
        }
        $collections = $collections->toJson();
        $results = Json::decode($collections);
        if (isset($options) && count($options) > 0) {
            $results['rows_per_page'] = $options['rows_per_page'] ?? [];
        }
        return $results;
    }

    public function getResponseCode(): ?int
    {
        return $this->__response_code ?? null;
    }

    public function getResponseMessages(): mixed
    {
        return $this->__response_messages;
    }

    public function getResponseResult(): mixed
    {
        return $this->__response_result;
    }

    public function setResponseCode(int $code)
    {
        $this->__response_code = $code;
        return $this;
    }

    public function setResponseResult(mixed $result)
    {
        $this->__response_result = $result;
        return $this;
    }

    public function setResponseMessages(mixed $message)
    {
        $message = $this->mustArray($message);
        $this->__response_messages = $message;
        return $this;
    }
}
