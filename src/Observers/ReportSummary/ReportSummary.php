<?php

namespace Zahzah\LaravelSupport\Observers\ReportSummary;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Zahzah\LaravelSupport\Models\ReportSummary\ReportSummary as ModelReportSummary;
use Zahzah\LaravelSupport\Supports\BaseObserver;

class ReportSummary extends BaseObserver{
    use Concerns\HasReportSummary;

    public $report_entity = 'ReportSummary';

    protected static $__report_summary;
    protected static $__report_classes;
    protected static $__report_class;
    protected static $__report_model;
    protected static $__report_types;
    protected static $__report_type;
    protected static $__is_rendering = true;

    public function booting(
        $report_model, string|array $classes
    ): self{
        static::$__report_model   = $report_model;
        static::$__report_classes = $this->mustArray($classes);
        static::$__is_rendering   = !$this->isObserverDisabled();
        return $this;
    }

    private function isObserverDisabled(): bool{
        return $this->inArray(\class_basename($this),static::$__report_model->getObserverExceptions());
    }

    public function getReportTypes(): array {
        return [
            ModelReportSummary::TRANSACTION_REPORT,
            ModelReportSummary::DAILY_REPORT,
            ModelReportSummary::MONTHLY_REPORT,
            ModelReportSummary::YEARLY_REPORT
        ];
    }

    public function resultReportSummary(Model $report_summary, array $props = []){
        $report_summary = $this->eachProps($props,$report_summary);
        $report_summary->save();
        return static::$__report_summary = $report_summary;
    }

    public function checkingDate(&$date,$format){
        if ($date instanceof Carbon) $date = $date->format($format);
    }

    public function createReportSummary(array $attributes): Model{
        if (!isset($attributes['flag'])) throw new \Exception('flag in report summary process is required');

        $report_type = static::$__report_type;
        switch ($report_type) {
            case ModelReportSummary::TRANSACTION_REPORT:
                $this->checkingDate($attributes['date'],'Y-m-d H:i:s');
                $date = $attributes['date'] ?? now()->format('Y-m-d H:i:s');
            break;
            case ModelReportSummary::DAILY_REPORT:
                $this->checkingDate($attributes['date'],'Y-m-d');
                $date = $attributes['date'] ?? now()->setTimezone(config('app.client_timezone'))->format('Y-m-d');
            break;
            case ModelReportSummary::MONTHLY_REPORT:
                $this->checkingDate($attributes['date'],'Y-m');

                $date = $attributes['date'] ?? now()->setTimezone(config('app.client_timezone'))->format('Y-m');
            break;
            case ModelReportSummary::YEARLY_REPORT:
                $this->checkingDate($attributes['date'],'Y');
                $date = $attributes['date'] ?? now()->setTimezone(config('app.client_timezone'))->format('Y');
            break;
        }

        $guard = [
            'morph'     => $attributes['morph'] ?? $this->getMorphClassFromEntity(),
            'flag'      => $attributes['flag'],
            'date_type' => static::$__report_type,
            'date'      => $date
        ];

        foreach ($this->getSpecificFields() as $key) $guard[$key] = $attributes[$key];
        $report_summary = $this->{$this->report_entity.'Model'}()->firstOrCreate($guard);


        if (isset($attributes['props']) && count($attributes['props']) > 0){
            $this->eachProps($attributes['props'],$report_summary);
            $report_summary->save();
        }
        return static::$__report_summary = $report_summary;
    }

    protected function eachProps(array $props,? Model $report_summary = null){
        $report_summary ??= static::$__report_summary;
        foreach ($props as $key => $prop) {
            if ($this->isArray($prop)){
                $report_summary->setAttribute($key,$prop);
            }else{
                $report_summary->{$key} = $prop;
            }
        }
        return static::$__report_summary = $report_summary;
    }

    public function render(? array $attributes = null){
        if (static::$__is_rendering){
            foreach (static::$__report_classes as $class){
                $class                  = app($class);
                static::$__report_class = $class;
                $reported_types         = static::$__report_types = $class->getReportTypes();
                foreach ($reported_types as $reported_type) {
                    static::$__report_type = $reported_type;
                    $class->render($attributes);
                }
            }
        }
    }
}
