<?php

namespace Zahzah\LaravelSupport\Middlewares;

use Illuminate\Http\Request;
use Zahzah\LaravelSupport\Concerns\{
    DatabaseConfiguration\HasModelConfiguration,
    Support\HasArray,
    Support\HasCallStatic
};
use Zahzah\LaravelSupport\Facades\LaravelSupport;
use Zahzah\LaravelSupport\Facades\Response;

class Middleware{
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
    public function handle(Request $request, \Closure $next){ 
        return Response::respondHandle($request,$next);
    }

    public function callCustomMethod(): array{
        return ['Model'];
    }
}