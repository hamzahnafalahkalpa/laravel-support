<?php

namespace Zahzah\LaravelSupport\Resources\Phone;

use Zahzah\LaravelSupport\Resources\ApiResource;

class ShowPhone extends ApiResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray(\Illuminate\Http\Request $request) : array{
      $arr = [
        'id'          => $this->id,
        'phone'       => $this->phone,
        'created_at'  => $this->created_at,
        'verified_at' => $this->verified_at
      ];
      
      return $arr;
  }
}