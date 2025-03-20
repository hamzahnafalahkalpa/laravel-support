<?php

namespace Hanafalah\LaravelSupport\Observers\ReportSummary\Concerns;

trait HasReportSummary
{
    public function getMorphClassFromEntity(): string
    {
        if (!isset($this->report_entity)) throw new \Exception('Report class not found');
        return $this->{$this->report_entity . 'Model'}()->getMorphClass();
    }
}
