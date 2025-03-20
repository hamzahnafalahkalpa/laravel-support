<?php

namespace Hanafalah\LaravelSupport\Commands;

use Hanafalah\LaravelSupport\{
    Commands\Concerns as CommandSupport,
    Concerns\Support as ConcernsSupport,
    Concerns
};
use Hanafalah\LaravelSupport\Concerns\Commands\PromptLayout;
use Hanafalah\LaravelSupport\Concerns\Support\HasCall;

abstract class BaseCommand extends \Illuminate\Console\Command
{
    use CommandSupport\PromptList;
    use Concerns\ServiceProvider\HasConfiguration;
    use Concerns\DatabaseConfiguration\HasModelConfiguration;
    use ConcernsSupport\HasRepository;
    use ConcernsSupport\HasArray;
    use PromptLayout;
    use HasCall;

    public function callCustomMethod()
    {
        return ['Model', 'Configuration'];
    }
}
