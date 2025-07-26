<?php

namespace Hanafalah\LaravelSupport\Resources\Unicode;

use Illuminate\Http\Request;
use Hanafalah\LaravelSupport\Resources\ApiResource;

class ShowUnicode extends ViewUnicode
{
  /**
   * Transform the resource into an array.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
   */
  public function toArray(Request $request): array
  {
    $arr = [
      'service' => $this->relationValidation('service', function () {
        return $this->service->toShowApi()->resolve();
      })
    ];
    $arr = $this->mergeArray(parent::toArray($request),$arr);
    return $arr;
  }
}
