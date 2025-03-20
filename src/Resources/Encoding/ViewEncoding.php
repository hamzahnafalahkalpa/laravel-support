<?php

namespace Hanafalah\LaravelSupport\Resources\Encoding;

use Hanafalah\LaravelSupport\Resources\ApiResource;
use Hanafalah\LaravelSupport\Resources\ModelHasEncoding\ShowModelHasEncoding;

class ViewEncoding extends ApiResource
{
  /**
   * Transform the resource into an array.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
   */
  public function toArray(\Illuminate\Http\Request $request): array
  {
    $arr = [
      'id'                   => $this->id,
      'name'                 => $this->name,
      'flag'                 => $this->flag,
      'is_not_update'        => $this->when($this->relationLoaded('modelHasEncoding') && isset($this->modelHasEncoding), function () {
        return ($this->modelHasEncoding->structure) ? true : false;
      }),
      'encoding_information' => $this->when($this->relationLoaded('modelHasEncoding') && isset($this->modelHasEncoding), function () {
        return new ShowModelHasEncoding($this->modelHasEncoding);
      }),
      'created_at'           => $this->created_at,
      'updated_at'           => $this->updated_at
    ];

    return $arr;
  }
}
