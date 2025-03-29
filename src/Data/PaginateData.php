<?php

namespace Hanafalah\LaravelSupport\Data;

use Hanafalah\LaravelSupport\Contracts\Data\PaginateData as DataPaginateData;
use Hanafalah\LaravelSupport\Supports\Data;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapName;

class PaginateData extends Data implements DataPaginateData
{
    public function __construct(
        #[MapInputName('per_page')]
        #[MapName('perPage')]
        public ?int $perPage = 50,
    
        #[MapInputName('columns')]
        #[MapName('columns')]
        public ?array $columns = ['*'],
    
        #[MapInputName('page_name')]
        #[MapName('pageName')]
        public ?string $pageName = 'page',
    
        #[MapInputName('page')]
        #[MapName('page')]
        public ?int $page = null,
    
        #[MapInputName('total')]
        #[MapName('total')]
        public ?int $total = null
    ){}
}