<?php

namespace Zahzah\LaravelSupport\Resources\ModelHasEncoding;

use Zahzah\LaravelSupport\Resources\ApiResource;

class ShowModelHasEncoding extends ApiResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray(\Illuminate\Http\Request $request) : array{
      $arr = [
        'id'                   => $this->id,
        'value'                => $this->value,
        'reference_id'         => $this->reference_id,
        'reference_type'       => $this->reference_type,
        'structure'            => $this->structure,
        'separator'            => $this->separator,
        'created_at'           => $this->created_at,
        'updated_at'           => $this->updated_at
      ];
      
      return $arr;
  }
}