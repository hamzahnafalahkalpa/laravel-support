<?php

namespace Hanafalah\LaravelSupport\Schemas\ReportSummary;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Hanafalah\LaravelSupport\{
    Supports
};
use Hanafalah\LaravelSupport\Contracts\Schemas\ReportSummary\ReportSummary as SchemaReportSummary;
use Hanafalah\LaravelSupport\Resources\ReportSummary\ViewReportSummary;

class ReportSummary extends Supports\PackageManagement implements SchemaReportSummary
{
    protected string $__entity = 'ReportSummary';

    public static $report_summary;

    protected array $__resources = [
        'view' => ViewReportSummary::class,
        'show' => ViewReportSummary::class
    ];

    public function viewUsingRelation(): array
    {
        return [];
    }

    protected function commonPaginate($paginate_options): LengthAwarePaginator
    {
        return $this->reportSummary()->with($this->viewUsingRelation())->paginate(...$this->arrayValues($paginate_options))->appends(request()->all());
    }

    public function prepareViewReportSummaryPaginate(int $perPage = 50, array $columns = ['*'], string $pageName = 'page', ?int $page = null, ?int $total = null): LengthAwarePaginator
    {
        $attributes ??= request()->all();

        $paginate_options = compact('perPage', 'columns', 'pageName', 'page', 'total');
        return static::$report_summary = $this->commonPaginate($paginate_options);
    }


    public function viewReportSummaryPaginate(int $perPage = 50, array $columns = ['*'], string $pageName = 'page', ?int $page = null, ?int $total = null): array
    {
        $paginate_options = compact('perPage', 'columns', 'pageName', 'page', 'total');
        return $this->transforming($this->__resources['view'], function () use ($paginate_options) {
            return $this->prepareViewReportSummaryPaginate(...$this->arrayValues($paginate_options));
        });
    }

    public function reportSummary(mixed $conditionals = null): Builder
    {
        $this->booting();
        return $this->{$this->__entity . 'Model'}()->conditionals($conditionals)->orderBy('date', 'desc');
    }
}
