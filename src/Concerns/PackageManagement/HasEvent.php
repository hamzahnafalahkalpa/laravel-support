<?php

namespace Hanafalah\LaravelSupport\Concerns\PackageManagement;

use Illuminate\Support\Facades\Event;
use Stancl\JobPipeline\JobPipeline;

trait HasEvent
{
    /**
     * Boot the events.
     *
     * @return void
     */
    protected function bootEvents()
    {
        foreach ($this->events() as $event => $listeners) {
            foreach ($listeners as $listener) {
                if ($listener instanceof JobPipeline) {
                    $listener = $listener->toListener();
                }
                Event::listen($event, $listener);
            }
        }
    }
}
