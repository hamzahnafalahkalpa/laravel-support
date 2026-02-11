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

        if (!Auth::check()) {
            return;
        }

        $user = $this->prepareUser();
        $userReference = $user->userReference;

        if (!$userReference) {
            return;
        }

        $role = $userReference->role;
        $roleId = $role?->getKey();

        request()->merge([
            'role_id' => $roleId,
            'is_show_in_acl' => true
        ]);

        $permissionModel = config('database.models.Permission');
        if (!$route_name || !$permissionModel || !\is_subclass_of($permissionModel, Model::class)) {
            return;
        }

        $permission = app($permissionModel);

        // Cache key includes role_id for proper isolation
        $cacheKey = 'permission-route-'.$route_name.'-role-'.$roleId;

        $route_permission = $this->setCache([
            'name'    => $cacheKey,
            'tags'    => ['permission','permission-route','permission-route-'.$route_name,'role-'.$roleId],
            'duration' => 24*60*60
        ], function() use ($permission, $route_name, $roleId) {
            return $permission->with(['recursiveModules','childs' => function($query) use ($roleId) {
                $query->asPermission()->showInAcl()
                     ->where(function($query){
                        $query->whereNull('props->show_in_data')
                              ->orWhere('props->show_in_data',false);
                     })
                     ->checkAccess($roleId);
            }])
            ->where("alias", $route_name)
            ->showInAcl()
            ->checkAccess($roleId)
            ->first();
        });

        if (!$route_permission && Response::getAclPermission() !== null) {
            $route_permission = Response::getAclPermission();
        }

        if (!$route_permission) {
            $this->__response['acl'] = null;
            return;
        }

        // Process recursive modules if they exist
        if ($route_permission->relationLoaded('recursiveModules')) {
            $this->recursiveModules($route_permission->recursiveModules);
        }

        // Get childs with data display - use cached relation if available
        $childs = $route_permission->childs()->asPermission()->showInData()->get();

        // Prepare data reference
        $data = isset($this->__response_result['data'])
            ? $this->__response_result['data']
            : $this->__response_result;

        if (!is_array($data)) {
            $this->__response['acl'] = $route_permission->toViewApi()->resolve();
            return;
        }

        $datas = array_is_list($data) ? $data : [$data];
        $isListData = array_is_list($data);

        $methodInfo = $this->checkingMethod();
        $methodType = $methodInfo[2] ?? null;

        if ($childs && count($childs) > 0) {
            // Cache FormRequest check results to avoid repeated reflection
            $accessCache = [];

            foreach ($datas as $index => &$dataItem) {
                if (!is_array($dataItem)) continue;

                request()->merge(['id' => $dataItem['id'] ?? null]);
                $dataItem['accessibility'] = [];

                foreach ($childs as $child) {
                    $alias = $child->alias;

                    // Use cached result if available
                    if (!isset($accessCache[$alias])) {
                        $accessCache[$alias] = $this->getCurrentFormRequestInstance($alias) ?? true;
                    }

                    $child->access = $accessCache[$alias];
                    $dataItem['accessibility'][Str::afterLast($alias, '.')] = $child->access;
                }
            }

            // Update the response result
            if ($isListData) {
                if (isset($this->__response_result['data'])) {
                    $this->__response_result['data'] = $datas;
                } else {
                    $this->__response_result = $datas;
                }
            } else {
                if (isset($this->__response_result['data'])) {
                    $this->__response_result['data'] = $datas[0];
                } else {
                    $this->__response_result = $datas[0];
                }
            }
        } else {
            if ($methodType !== 'DELETE') {
                foreach ($datas as $index => &$dataItem) {
                    if (is_array($dataItem)) {
                        $dataItem['accessibility'] = null;
                    }
                }

                if ($isListData) {
                    if (isset($this->__response_result['data'])) {
                        $this->__response_result['data'] = $datas;
                    } else {
                        $this->__response_result = $datas;
                    }
                } else {
                    if (isset($this->__response_result['data'])) {
                        $this->__response_result['data'] = $datas[0];
                    } else {
                        $this->__response_result = $datas[0];
                    }
                }
            }
        }

        $this->__response['acl'] = $route_permission->toViewApi()->resolve();
    }

    private function recursiveModules(&$permissions, array &$accessCache = []){
        if (empty($permissions)) {
            return;
        }

        // Collect all permission IDs to batch load childs
        $permissionIds = [];
        foreach ($permissions as $permission) {
            if (!$permission->relationLoaded('childs')) {
                $permissionIds[] = $permission->getKey();
            }
        }

        // Batch load childs for all permissions at once (avoid N+1)
        if (!empty($permissionIds)) {
            $permissionModel = $permissions->first();
            $childsMap = $permissionModel->newQuery()
                ->whereIn('parent_id', $permissionIds)
                ->showInAcl()
                ->asPermission()
                ->get()
                ->groupBy('parent_id');

            foreach ($permissions as &$permission) {
                $permission->setRelation('childs', $childsMap[$permission->getKey()] ?? collect());
            }
        }

        foreach ($permissions as &$permission) {
            if ($permission->access) {
                $alias = $permission->alias;
                // Use cached result if available
                if (!isset($accessCache[$alias])) {
                    $accessCache[$alias] = $this->getCurrentFormRequestInstance($alias) ?? true;
                }
                $permission->access = $accessCache[$alias];
            }

            // Recurse if needed
            if ($permission->relationLoaded('recursiveModules') &&
                $permission->recursiveModules &&
                count($permission->recursiveModules) > 0) {
                $this->recursiveModules($permission->recursiveModules, $accessCache);
            }
        }
    }

    /**
     * Cache for route method info to avoid repeated lookups
     */
    private static array $methodCache = [];

    /**
     * Cache for FormRequest validation results
     */
    private static array $formRequestCache = [];

    private function checkingMethod(?string $alias = null){
        $cacheKey = $alias ?? '__current__';

        if (isset(self::$methodCache[$cacheKey])) {
            return self::$methodCache[$cacheKey];
        }

        $route = isset($alias) ? Route::getRoutes()->getByName($alias) : Route::getCurrentRoute();
        if (!$route) {
            return self::$methodCache[$cacheKey] = null;
        }

        $action = $route->getActionName();
        if (!str_contains($action, '@')) {
            return self::$methodCache[$cacheKey] = null;
        }

        return self::$methodCache[$cacheKey] = [...explode('@', $action), ...$route->methods()];
    }

    private function getCurrentFormRequestInstance(?string $alias = null): mixed
    {
        // Return cached result if available
        $cacheKey = $alias ?? '__current__';
        if (isset(self::$formRequestCache[$cacheKey])) {
            return self::$formRequestCache[$cacheKey];
        }

        $methodInfo = $this->checkingMethod($alias);
        if (!$methodInfo) {
            return self::$formRequestCache[$cacheKey] = false;
        }

        [$controllerClass, $methodName, $methodType] = $methodInfo;

        if (!class_exists($controllerClass)) {
            return self::$formRequestCache[$cacheKey] = null;
        }

        try {
            $reflection = new ReflectionClass($controllerClass);
            if (!$reflection->hasMethod($methodName)) {
                return self::$formRequestCache[$cacheKey] = null;
            }

            $method = $reflection->getMethod($methodName);
            $params = $method->getParameters();

            foreach ($params as $param) {
                $type = $param->getType();
                if ($type instanceof ReflectionNamedType && is_subclass_of($type->getName(), FormRequest::class)) {
                    try {
                        app($type->getName());
                        return self::$formRequestCache[$cacheKey] = true;
                    } catch (\Throwable $th) {
                        return self::$formRequestCache[$cacheKey] = false;
                    }
                }
            }
        } catch (\Throwable $th) {
            return self::$formRequestCache[$cacheKey] = false;
        }

        return self::$formRequestCache[$cacheKey] = false;
    }

    /**
     * Clear static caches (for Octane)
     */
    public static function flushResponseCaches(): void
    {
        self::$methodCache = [];
        self::$formRequestCache = [];
    }

    private function prepareUser()
    {
        // Use already authenticated user instead of querying again
        $user = auth()->user();

        // Only load userReference if not already loaded
        if (!$user->relationLoaded('userReference')) {
            $user->load('userReference.role');
        } elseif ($user->userReference && !$user->userReference->relationLoaded('role')) {
            $user->userReference->load('role');
        }

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
