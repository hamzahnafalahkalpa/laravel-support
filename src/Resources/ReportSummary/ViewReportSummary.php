<?php

namespace Hanafalah\LaravelSupport\Resources\ReportSummary;

use Illuminate\Http\Request;
use Hanafalah\LaravelSupport\Resources\ApiResource;

class ViewReportSummary extends ApiResource
{
    public function toArray(Request $request): array
    {
        $arr = [
            'id'       => $this->id,
            'columns'  => []
        ];

        $props = $this->getPropsData() ?? [];
        foreach ($props['columns'] as $key => $value) {
            $arr['columns'][$key] = $value;
        }
        return $arr;
    }
}
