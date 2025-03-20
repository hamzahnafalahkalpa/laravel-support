<?php

namespace Zahzah\LaravelSupport\Resources\ReportSummary;

use Illuminate\Http\Request;
use Zahzah\LaravelSupport\Resources\ApiResource;

class ViewReportSummary extends ApiResource{
    public function toArray(Request $request): array
    {
        $arr = [
            'id'       => $this->id,
            'columns'  => []
        ];

        $props = $this->getPropsData() ?? [];
        foreach ($props['columns'] as $key => $value){
            $arr['columns'][$key] = $value;
        }
        return $arr;
    }
}