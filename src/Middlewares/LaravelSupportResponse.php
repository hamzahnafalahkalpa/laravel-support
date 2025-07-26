<?php

namespace Hanafalah\LaravelSupport\Middlewares;

use Closure;
use Hanafalah\LaravelSupport\{
    Concerns\Support\HasResponse,
};
use Hanafalah\LaravelSupport\Facades\Response;

class LaravelSupportResponse
{
    use HasResponse;

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle($request, Closure $next)
    {
        return Response::respondHandle($request, $next);
    }
}
