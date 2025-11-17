<?php

namespace Hanafalah\LaravelSupport\Resources\Timezone;

use Illuminate\Http\Request;
use Hanafalah\LaravelSupport\Resources\ApiResource;

class ViewTimezone extends ApiResource
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
      'id'        => $this->id,
      'name'      => $this->name,
      'label'     => $this->label
    ];
    return $arr;
  }
}
