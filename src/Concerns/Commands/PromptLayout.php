<?php

namespace Hanafalah\LaravelSupport\Concerns\Commands;

trait PromptLayout
{
    protected function cardLine(string $title, callable $callback): self
    {
        $this->line('------------------------------------');
        $this->info("Starting $title...");
        $callback();
        $this->info('End for <fg=yellow>' . $title . '</>...');
        $this->line('------------------------------------');
        $this->newLine();
        return $this;
    }
}
