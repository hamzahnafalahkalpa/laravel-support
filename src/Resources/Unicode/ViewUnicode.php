<?php

namespace Hanafalah\LaravelSupport\Resources\Unicode;

use Illuminate\Http\Request;
use Hanafalah\LaravelSupport\Resources\ApiResource;

class ViewUnicode extends ApiResource
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
      'parent_id' => $this->parent_id,
      'name'      => $this->name,
      'flag'      => $this->flag,
      'label'     => $this->label,
      'ordering'  => $this->ordering,
      'status'    => $this->status,
      'service'   => $this->relationValidation('service', function () {
          return $this->service->toViewApi()->resolve();
      }),
      'childs' => $this->relationValidation('childs',function(){
          return $this->childs->transform(function($child){
              return $child->toViewApi();
          });
      }),
      'tariff_components' => $this->relationValidation('tariffComponents', function () {
          return $this->tariffComponents->transform(function ($tariffComponent) {
              return $tariffComponent->toViewApi();
          });
      }),
      'created_at' => $this->created_at,
      'updated_at' => $this->updated_at
    ];
    return $arr;
  }
}
