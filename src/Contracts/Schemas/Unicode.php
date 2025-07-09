<?php

namespace Hanafalah\LaravelSupport\Contracts\Schemas;

use Hanafalah\LaravelSupport\Contracts\Supports\DataManagement;

/**
 * @see \Hanafalah\LaravelSupport\Schemas\Unicode
 * @method self setParamLogic(string $logic, bool $search_value = false, ?array $optionals = [])
 * @method self conditionals(mixed $conditionals)
 * @method mixed export(string $type)
 * @method bool deleteUnicode()
 * @method bool prepareDeleteUnicode(? array $attributes = null)
 * @method mixed getUnicode()
 * @method ?Model prepareShowUnicode(?Model $model = null, ?array $attributes = null)
 * @method array showUnicode(?Model $model = null)
 * @method Collection prepareViewUnicodeList()
 * @method array viewUnicodeList()
 * @method LengthAwarePaginator prepareViewUnicodePaginate(PaginateData $paginate_dto)
 * @method array viewUnicodePaginate(?PaginateData $paginate_dto = null)
 * @method array storeUnicode(?UnicodeData $unicode_dto = null)
 */
interface Unicode extends DataManagement {}
