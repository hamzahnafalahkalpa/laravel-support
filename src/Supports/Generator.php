<?php

namespace Hanafalah\LaravelSupport\Supports;

use Illuminate\Container\Container;
use Hanafalah\LaravelSupport\Concerns\JsonGenerator;
use Hanafalah\LaravelSupport\FileRepository;

class Generator extends FileRepository
{
    use JsonGenerator;

    public function __construct(Container $app, ...$args)
    {
        parent::__construct($app, ...$args);
        $this->__json = [];
        $this->__json_template = $this->parse($this->getContent(__DIR__ . '/../versioning.json'));
    }
}
