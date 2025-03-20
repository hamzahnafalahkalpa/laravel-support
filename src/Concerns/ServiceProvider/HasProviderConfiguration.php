<?php

namespace Hanafalah\LaravelSupport\Concerns\ServiceProvider;

trait HasProviderConfiguration
{
    /**
     * Registers the providers from the given array in the application.
     *
     * This method will only register the provider if the file exists.
     *
     * @param array $providers
     *
     * @return $this
     */
    protected function validProviders($providers)
    {
        foreach ($providers as $path => $provider) {
            if (file_exists($path)) $this->app->register($provider);
        }
        return $this;
    }
}
