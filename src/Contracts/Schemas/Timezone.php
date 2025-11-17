<?php

namespace Hanafalah\LaravelSupport\Contracts\Schemas;

use Hanafalah\LaravelSupport\Contracts\Data\TimezoneData;
//use Hanafalah\LaravelSupport\Contracts\Data\TimezoneUpdateData;
use Hanafalah\LaravelSupport\Contracts\Supports\DataManagement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @see \Hanafalah\LaravelSupport\Schemas\Timezone
 * @method mixed export(string $type)
 * @method self conditionals(mixed $conditionals)
 * @method array updateTimezone(?TimezoneData $timezone_dto = null)
 * @method Model prepareUpdateTimezone(TimezoneData $timezone_dto)
 * @method bool deleteTimezone()
 * @method bool prepareDeleteTimezone(? array $attributes = null)
 * @method mixed getTimezone()
 * @method ?Model prepareShowTimezone(?Model $model = null, ?array $attributes = null)
 * @method array showTimezone(?Model $model = null)
 * @method Collection prepareViewTimezoneList()
 * @method array viewTimezoneList()
 * @method LengthAwarePaginator prepareViewTimezonePaginate(PaginateData $paginate_dto)
 * @method array viewTimezonePaginate(?PaginateData $paginate_dto = null)
 * @method array storeTimezone(?TimezoneData $timezone_dto = null)
 * @method Collection prepareStoreMultipleTimezone(array $datas)
 * @method array storeMultipleTimezone(array $datas)
 */

interface Timezone extends Unicode
{
    public function prepareStoreTimezone(TimezoneData $timezone_dto): Model;
}