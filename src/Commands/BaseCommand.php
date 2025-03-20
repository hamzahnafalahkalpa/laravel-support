<?php

namespace Zahzah\LaravelSupport\Commands;

use Zahzah\LaravelSupport\{
    Commands\Concerns as CommandSupport,
    Concerns\Support as ConcernsSupport,
    Concerns
};
use Zahzah\LaravelSupport\Concerns\Commands\PromptLayout;
use Zahzah\LaravelSupport\Concerns\Support\HasCall;

abstract class BaseCommand extends \Illuminate\Console\Command{
    use CommandSupport\PromptList;
    use Concerns\ServiceProvider\HasConfiguration;
    use Concerns\DatabaseConfiguration\HasModelConfiguration;
    use ConcernsSupport\HasRepository; 
    use ConcernsSupport\HasArray;
    use PromptLayout;
    use HasCall;

    public function callCustomMethod(){
        return ['Model','Configuration'];
    }
}