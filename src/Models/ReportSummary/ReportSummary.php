<?php

namespace Zahzah\LaravelSupport\Models\ReportSummary;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Zahzah\LaravelHasProps\Concerns\HasProps;
use Zahzah\LaravelSupport\Models\BaseModel;
use Zahzah\LaravelSupport\Resources\ReportSummary\ViewReportSummary;

class ReportSummary extends BaseModel{
    use HasUlids, HasProps;

    const TRANSACTION_REPORT   = 'TRANSACTION';
    const DAILY_REPORT         = 'DAILY';
    const MONTHLY_REPORT       = 'MONTHLY';
    const YEARLY_REPORT        = 'YEARLY';

    public $timestamps    = false;
    protected $keyType    = "string";
    protected $primaryKey = "id";
    protected $list = [
        'id', 'morph', 'flag', 'date_type', 'date', 'tenant_id', 'props'
    ];

    protected $casts = [
        'flag'       => 'string',
        'date_type'  => 'string',
        'date'       => 'immutable_date'
    ];

    public function toViewApi(){
        return new ViewReportSummary($this);
    }
}