<?php

namespace Hanafalah\LaravelSupport\Contracts;

use Hanafalah\LaravelSupport\Contracts\Supports\DataManagement;

interface LaravelSupport extends DataManagement
{
    public function showModeModel(?bool $is_show = true): self;
    public function isShowModel(): bool;
}
