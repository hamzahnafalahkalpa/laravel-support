<?php

namespace Hanafalah\LaravelSupport\Concerns\Support;

trait HasMicrotenant
{
    protected $__is_multitenancy = false;

    /**
     * Returns true if the multitenancy feature is enabled.
     *
     * @return bool
     */
    protected function isMultitenancy(): bool
    {
        $loader = require base_path('vendor/autoload.php');
        if (isset($loader->getPrefixesPsr4()['Hanafalah\\MicroTenant\\'])) {
            if (config('micro-tenant.tenancy.enabled')){
                $this->__is_multitenancy = true;
            }
        }
        return $this->__is_multitenancy;
    }
}
