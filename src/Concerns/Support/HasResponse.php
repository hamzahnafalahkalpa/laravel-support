<?php

namespace Hanafalah\LaravelSupport\Concerns\Support;

use Illuminate\Database\Eloquent\{
    Casts\Json,
    Model
};
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Hanafalah\ApiHelper\Facades\ApiAccess;
use Hanafalah\LaravelPermission\Resources\Permission\ViewPermission;
use Hanafalah\LaravelSupport\Facades\Response;

trait HasResponse
{
    use HasArray;

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

        $this->__response = [
            'meta' => [
                'code'     => $this->__response_code,
                'success'  => $success,
                'messages' => $this->mustArray($this->__response_messages),
            ],
            'data' => $this->__response_result,
        ];
        if ($success) {
            $this->renderAclResponse();
        }
        ksort($this->__response);
        return $this->__response;
    }

    private function renderAclResponse()
    {
        $route      = request()->route();
        $route_name = $route ? $route->getName() : null;
        if (auth()->check()) {
            $user = $this->prepareUser();

            $permission = app(config('database.models.Permission'));
            if (isset($route_name) && \is_subclass_of($permission, Model::class)) {
                $permission = $permission->where("alias", $route_name)->first();
                if (!isset($permission) && Response::getAclPermission() !== null) {
                    $permission = Response::getAclPermission();
                }

                if (isset($permission)) {
                    $permission->load(['childs' => fn($q) => $q->showInAcl()]);
                    $role = $user->userReference->role;
                    if (isset($role)) {
                        $permissions = $this->getNextPermission($role, $permission);
                        if (isset($permissions) && count($permissions) > 0) {
                            $ids = array_column($permission->childs->toArray(), 'id');
                            foreach ($permissions as $role_permission) {
                                $key = array_search($role_permission->getKey(), $ids);
                                if ($key !== false) $permission->childs[$key]->access = true;
                            }
                        }
                        $this->__response['acl'] = $permission->toViewApi()->resolve();
                    }
                }
            }
        }
    }

    private function getNextPermission($role, $permission)
    {
        $role->load([
            'permissions' => function ($query) use ($permission) {
                $query->showInAcl()->parentId($permission->getKey());
            }
        ]);
        return $role->permissions;
    }

    private function prepareUser()
    {
        $user           = $this->UserModel()->find(ApiAccess::getUser()->getKey());
        $user_reference = &$user->userReference;
        $role_id        = $user_reference->role_id;
        $role           = $user_reference->roles()->where('role_id', $role_id)->first();
        $user_reference->setRelation('role', $role);
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
