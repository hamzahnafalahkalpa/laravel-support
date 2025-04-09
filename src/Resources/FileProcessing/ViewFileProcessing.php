<?php

namespace Hanafalah\LaravelSupport\Resources\FileProcessing;

use Illuminate\Http\Request;
use Hanafalah\LaravelSupport\Resources\ApiResource;

class ViewFileProcessing extends ApiResource
{
    public function toArray(Request $request): array
    {
        $arr = [
            'id'     => $this->id,
            'file'   => $this->getFile()
        ];
        return $arr;
    }
}
