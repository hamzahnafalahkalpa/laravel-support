<?php

namespace Zahzah\LaravelSupport\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Zahzah\LaravelSupport\Concerns\DatabaseConfiguration\HasModelConfiguration;
use Zahzah\LaravelSupport\Concerns\Resources\HasDateNormalize;
use Zahzah\LaravelSupport\Concerns\Support\HasArray;

class ApiResource extends JsonResource
{
    use HasArray, HasModelConfiguration, HasDateNormalize;

    public function __construct($resource)
    {
        $this->resource = $resource;
        $this->normalize();
    }

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray(\Illuminate\Http\Request $request): array
    {
        return parent::toArray($request);
    }

    public function callCustomMethod(){
        return ['Model'];
    }

    public function resolveNow($resource){
        // return $resource->resolve();
        return json_decode(json_encode($resource),true);
    }

    public function getPropsData(){
        $fillable   = $this->getFillable();
        $attributes = $this->getAttributes();
        if ($this->usesTimestamps()) $fillable = $this->mergeArray($fillable,['created_at','updated_at']);
        $fillable = $this->mergeArray($fillable,['deleted_at']);
        $diff = array_diff_key($attributes, array_flip($fillable));
        return  $diff == [] ? null : $diff;
    }
}