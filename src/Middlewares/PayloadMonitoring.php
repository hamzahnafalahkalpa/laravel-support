<?php

namespace Hanafalah\LaravelSupport\Middlewares;

use Illuminate\Http\Request;
use Hanafalah\LaravelSupport\Middlewares\Middleware;

class PayloadMonitoring extends Middleware
{

    private $__config;

    protected $__entity = 'PayloadMonitoring';

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, \Closure $next)
    {
        $this->__config = config('laravel-support.payload_monitoring');
        if ($this->__config['enabled']) {
            $url = $request->getRequestUri();
            $url = '/' . ltrim($url, '/');
            $payloadMonitoring = $this->{$this->__entity . 'Model'}()::create([
                'url'      => $url,
                'start_at' => now(),
            ]);
            $response = $next($request);
            //UPDATE PAYLOAD MONITORING
            $endAt = now();
            $timeDifference = $endAt->diffInMilliseconds($payloadMonitoring->start_at) / 1000;
            $payloadMonitoring->update([
                'end_at'           => $endAt,
                'time_difference'  => $timeDifference,
                'speed_category'   => $this->getSpeedCategory($timeDifference),
            ]);
        }
        return $response;
    }

    /**
     * Determine the speed category based on the time difference.
     * 
     * @param int $timeDifference The time difference to categorize
     * @return string The speed category ('fast', 'medium', or 'slow')
     */
    private function getSpeedCategory($timeDifference)
    {
        $configs = config('laravel-support.payload_monitoring');
        foreach ($configs as $category => $time) {
            if ($timeDifference <= $time) {
                return $category;
            }
        }
        return 'Very bad';
    }
}
