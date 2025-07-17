<?php

namespace Hanafalah\LaravelSupport\Middlewares;

use Illuminate\Http\Request;
use Hanafalah\LaravelSupport\Concerns\{
    DatabaseConfiguration\HasModelConfiguration,
    Support\HasArray,
    Support\HasCallStatic
};
use Hanafalah\LaravelSupport\Facades\LaravelSupport;
use Hanafalah\LaravelSupport\Facades\Response;

class Middleware
{
    use HasModelConfiguration;
    use HasArray;
    use HasCallStatic;

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, \Closure $next)
    {
        return Response::respondHandle($request, $next);
    }

    public function callCustomMethod(): array
    {
        return ['Model'];
    }
}
