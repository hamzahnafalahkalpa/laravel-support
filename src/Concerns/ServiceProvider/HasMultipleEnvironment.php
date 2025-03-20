<?php

namespace Hanafalah\LaravelSupport\Concerns\ServiceProvider;

use Illuminate\Support\Facades\App;

trait HasMultipleEnvironment
{

    /**
     * Registers the environment file to be used by Laravel.
     *
     * It will look for a file named `.env.<env>` in the root of the project, where `<env>`
     * is the value of the `app.env` configuration. If such a file does not exist, it will
     * fall back to the default `.env` file.
     *
     * @return $this The current instance of the class.
     */
    public function registerEnvironment(): self
    {
        $app  = config('app.env');
        $path = base_path('.env.' . $app);
        if (!file_exists($path)) $path = base_path('.env');
        App::useEnvironmentPath($path);
        return $this;
    }
}
