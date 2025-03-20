<?php

namespace Zahzah\LaravelSupport;

use Closure;
use Exception;
use Illuminate\Container\Container;
use Zahzah\LaravelSupport\Concerns;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Support\Facades\Request;
use Zahzah\ApiHelper\Exceptions\UnauthorizedAccess;
use Zahzah\LaravelSupport\Contracts\Response as ContractsResponse;
use Zahzah\LaravelSupport\Supports\PackageManagement;

class Response extends PackageManagement implements ContractsResponse
{
    use Concerns\Support\HasResponse;
    use Concerns\Support\ErrorHandling;
    use Concerns\Support\HasUserInfo;
    
    private $__response;
    protected static $__setup_permission = null;

    public function response(mixed $result = null,? int $code = null,? string $message = null){
        return $this->sendResponse($result,$code ?? $this->getResponseCode() ?? 200,$message ?? $this->getResponseMessages() ?? 'Success.');
    }

    public function setAclPermission(string $alias){
        static::$__setup_permission = $this->PermissionModel()->where('alias', $alias)->firstOrFail();
    }

    public function getAclPermission(){
        return static::$__setup_permission;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function respondHandle($request,Closure $next){
        $response = $next($request);
        if ($response->getStatusCode() < 400){
            if (Request::wantsJson() && !is_array($response)) {
                return $this->response($response->original);
            }
        }
        return $response;
    }


    public function exceptionRespond(Exceptions $exceptions): void{
        if (Request::wantsJson()) {
            $exceptions->render(function (Exception $e) {
                $this->catch($e);
                $err = $e->getMessage();
                if ($err == '') $err = $this->getResponseMessages();
                switch (true) {
                    case $e instanceof \Illuminate\Validation\ValidationException:
                    case $e instanceof \Illuminate\Database\QueryException:
                        $code = 422;
                    break;
                    case $e instanceof \Illuminate\Auth\AuthenticationException:
                        $code = 401;
                    break;
                    case $e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException:
                        $code = 404;
                    break;
                    case $e instanceof \Firebase\JWT\ExpiredException:
                    case $e instanceof UnauthorizedAccess:
                        $code = 401;
                    break;
                }
                return $this->sendResponse(null,$code ?? 403,$err);
            });
        }
    }
}