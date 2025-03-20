<?php

namespace Hanafalah\LaravelSupport\Concerns\DatabaseConfiguration;

trait HasConnectionConfiguration
{

    protected array $__connection_config;

    /**
     * Retrieves the models configuration associated with the current instance.
     *
     * @return array The models configuration associated with the current instance.
     */
    protected function getAppConnectionConfig(): array
    {
        return $this->__connection_config ??= config('database.connections');
    }

    /**
     * 
     * Sets the models configuration associated with the current instance.
     *
     * Merges the given models configuration with the existing one, and sets the
     * result as the new models configuration associated with the current instance.
     *
     * @param array $models The models configuration to set.
     *
     * @return static The current instance.
     */


    protected function setAppConnections(array $connections = []): self
    {
        config([
            'database.connections' => $this->__connection_config = $this->mergeArray($connections, $this->getAppConnectionConfig())
        ]);
        return $this;
    }
}
