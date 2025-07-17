<?php

namespace Hanafalah\LaravelSupport\Resources\ImageProcessing;

use Illuminate\Http\Request;
use Hanafalah\LaravelSupport\Resources\ApiResource;

class ViewImage extends ApiResource
{
    public function toArray(Request $request): array
    {
        $arr = [
            'id'     => $this->id,
            'image'  => $this->getFile()
        ];
        return $arr;
    }
}
