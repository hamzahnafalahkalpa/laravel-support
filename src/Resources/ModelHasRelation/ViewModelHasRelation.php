<?php

namespace Hanafalah\LaravelSupport\Resources\ModelHasRelation;

use Hanafalah\LaravelSupport\Resources\ApiResource;

class ViewModelHasRelation extends ApiResource
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
      'id' => $this->id,
      'model_id'   => $this->model_id,
      'model_type' => $this->model_type,
      'model'      => $this->prop_model,
      'reference_id'   => $this->reference_id,
      'reference_type' => $this->reference_type,
      'reference'      => $this->prop_reference,
    ];
    return $arr;
  }
}
