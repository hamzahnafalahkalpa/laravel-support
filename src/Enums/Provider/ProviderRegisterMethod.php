<?php

namespace Hanafalah\LaravelSupport\Enums\Provider;

enum ProviderRegisterMethod: string
{
    case MODEL            =  'Model';
    case MIGRATION        =  'Migration';
    case PROVIDER         =  'Provider';
    case CONFIG           =  'Config';
    case DATABASE         =  'Database';
    case NAMESPACE        =  'Namespace';
    case ROUTE            =  'Route';
    case VIEWS            =  'Views';
}
