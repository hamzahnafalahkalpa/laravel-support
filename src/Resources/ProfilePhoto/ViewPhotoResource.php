<?php

namespace Hanafalah\LaravelSupport\Resources\ProfilePhoto;

use Illuminate\Http\Request;
use Hanafalah\LaravelSupport\Resources\ApiResource;

class ViewPhotoResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $arr = [
            'id'       => $this->id,
            'profile'  => $this->profile,
            'url_profile' => $this->getFullUrl()
        ];
        return $arr;
    }
}
