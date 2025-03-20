<?php

namespace Zahzah\LaravelSupport\Supports;

use Zahzah\LaravelSupport\Concerns\{
    Support, DatabaseConfiguration
};

class BaseObserver {
    use Support\HasRequest,
        Support\HasArray,
        Support\HasJson,
        Support\HasCallStatic,
        DatabaseConfiguration\HasModelConfiguration;

    public function callCustomMethod(): array{
        return ['Model'];        
    }
}