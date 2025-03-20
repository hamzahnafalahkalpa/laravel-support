<?php

namespace Hanafalah\LaravelSupport\Contracts;

interface LaravelSupport extends DataManagement
{
    public function showModeModel(?bool $is_show = true): self;
    public function isShowModel(): bool;
}
