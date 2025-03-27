<?php

namespace Hanafalah\LaravelSupport\Commands;

class InstallMakeCommand extends EnvironmentCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'support:install';


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
        $provider = 'Hanafalah\LaravelSupport\LaravelSupportServiceProvider';

        $this->comment('Installing Support...');
        $this->callSilent('vendor:publish', [
            '--provider' => $provider,
            '--tag'      => 'config'
        ]);
        $this->info('✔️  Created config/laravel-support.php');

        $this->callSilent('vendor:publish', [
            '--provider' => $provider,
            '--tag'      => 'stubs'
        ]);
        $this->info('✔️  Created Stubs/LaravelSupportStubs');

        $this->callSilent('vendor:publish', [
            '--provider' => $provider,
            '--tag'      => 'providers'
        ]);

        $this->info('✔️  Created LaravelSupportServiceProvider.php');

        $this->callSilent('vendor:publish', [
            '--provider' => $provider,
            '--tag'      => 'migrations'
        ]);

        $this->info('✔️  Created migrations');

        if (!$this->isMultitenancy()) {
            $migrations = $this->canMigrate();

            $this->callSilent('migrate', [
                '--path' => $migrations
            ]);

            $this->info('✔️  App table migrated');
        }


        $this->comment('hanafalah/laravel-support installed successfully.');
    }
}
