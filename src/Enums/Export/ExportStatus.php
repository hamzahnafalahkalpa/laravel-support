<?php

namespace Hanafalah\LaravelSupport\Enums\Export;

enum ExportStatus: string
{
    case PENDING = 'PENDING';
    case PROCESSING = 'PROCESSING';
    case COMPLETED = 'COMPLETED';
    case FAILED = 'FAILED';
}
