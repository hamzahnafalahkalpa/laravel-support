<?php

namespace Hanafalah\LaravelSupport\Commands;

use Illuminate\Support\Str;

class AddPackageCommand extends EnvironmentCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'support:add-package {package? : Package Provider}';


    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command ini digunakan untuk installing awal';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $package_model = config('laravel-support.package_model_list');
        if (!isset($package_model)) throw new \Exception('Package model not found');

        $package_model = app($package_model);
        if (!isset($package_model)){
            $package_model = $this->{$package_model.'Model'}();
            if (!isset($package_model)) throw new \Exception('Package model not found');
        }
        $id = $this->ask('Masukkan id package model !');
        $package_model = $package_model->findOrFail($id);

        $package = $original_package_name = $this->argument('package') ?? $this->ask('Masukkan package name !');
        $package = \explode('/',$package);
        $package[0] = Str::studly($package[0]);
        $package[1] = $provider = Str::studly($package[1]);
        $provider   = \implode('\\',$package).'\\'.$provider.'ServiceProvider';

        $packages = $package_model->packages ?? [];

        $packages[$original_package_name] = [
            'provider' => $provider,
        ];

        $package_model->setAttribute('packages',$packages);
        $package_model->save();
    }
}
