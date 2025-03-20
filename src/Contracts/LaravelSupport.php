<?php

namespace Zahzah\LaravelSupport\Contracts;

interface LaravelSupport extends DataManagement
{
    public function showModeModel(? bool $is_show = true): self;
    public function isShowModel(): bool;
}