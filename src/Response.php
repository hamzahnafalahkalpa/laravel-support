<?php

namespace Hanafalah\LaravelSupport;

use Closure;
use Exception;
use Hanafalah\LaravelSupport\Concerns;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Support\Facades\Request;
use Hanafalah\ApiHelper\Exceptions\UnauthorizedAccess;
use Hanafalah\LaravelSupport\Contracts\Response as ContractsResponse;
use Hanafalah\LaravelSupport\Supports\PackageManagement;
use Illuminate\Support\Facades\Auth;

class Response extends PackageManagement implements ContractsResponse
{
    use Concerns\Support\HasResponse;
    use Concerns\Support\ErrorHandling;
    use Concerns\Support\HasUserInfo;

    private $__response;
    protected static $__setup_permission = null;

    public function response(mixed $result = null, ?int $code = null, ?string $message = null)
    {
        return $this->sendResponse($result, $code ?? $this->getResponseCode() ?? 200, $message ?? $this->getResponseMessages() ?? 'Success.');
    }


    public function setAclPermission(string $alias)
    {
        static::$__setup_permission = $this->PermissionModel()->where('alias', $alias)->firstOrFail();
    }

    public function getAclPermission()
    {
        return static::$__setup_permission;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function respondHandle($request, Closure $next)
    {
        $response = $next($request);
        if ($response->getStatusCode() < 400  && $this->hasAppCode()) {
            // Skip transformation for binary/file responses (PDF, images, etc.)
            if ($this->isBinaryResponse($response)) {
                return $response;
            }
            if (Request::wantsJson() && !is_array($response)) {
                return $this->response($response->original);
            }
        }
        return $response;
    }

    /**
     * Check if response is a binary/file response that should not be transformed.
     *
     * @param mixed $response
     * @return bool
     */
    private function isBinaryResponse($response): bool
    {
        $contentType = $response->headers->get('Content-Type', '');

        $binaryTypes = [
            'application/pdf',
            'application/octet-stream',
            'image/',
            'audio/',
            'video/',
            'application/zip',
            'application/x-rar',
        ];

        foreach ($binaryTypes as $type) {
            if (str_contains($contentType, $type)) {
                return true;
            }
        }

        return false;
    }

    private function hasAppCode(): bool{
        return request()->headers->get('appcode') !== null;
    }

    public function exceptionRespond(Exceptions $exceptions): void
    {
        if (Request::wantsJson() && $this->hasAppCode()) {
            $exceptions->render(function (Exception $e) {
                $this->catch($e);
                $err = $e->getMessage();
                if ($err == '') $err = $this->getResponseMessages();
                // return $this->sendResponse(null, $code ?? 403, $err);
                switch (true) {
                    case $e instanceof \Illuminate\Validation\ValidationException:
                    case $e instanceof \Illuminate\Database\QueryException:
                        $code = 422;
                        if (!Auth::check()){
                            $code = 401;
                            $err = $e->getMessage();
                        }
                    break;
                    case $e instanceof \Illuminate\Auth\AuthenticationException:
                        $code = 401;
                    break;
                    case $e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException:
                        $code = 404;
                        if (!Auth::check()){
                            $code = 401;
                            $err = 'Unauthorized';
                        }
                    break;
                    case $e instanceof \Firebase\JWT\ExpiredException:
                    case $e instanceof UnauthorizedAccess:
                        $code = 401;
                    break;
                }   
                if (!Auth::check()){
                    $code = 401;
                    $err = 'Unauthorized';
                }
                return $this->sendResponse(null, $code ?? 403, $err);
            });
        }
    }
}
