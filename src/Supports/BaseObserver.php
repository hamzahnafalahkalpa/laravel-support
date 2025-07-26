<?php

namespace Hanafalah\LaravelSupport\Supports;

use Hanafalah\LaravelSupport\Concerns\{
    Support,
    DatabaseConfiguration
};

class BaseObserver
{
    use Support\HasRequest,
        Support\HasArray,
        Support\HasJson,
        Support\HasCallStatic,
        DatabaseConfiguration\HasModelConfiguration;

    public function callCustomMethod(): array
    {
        return ['Model'];
    }
}
