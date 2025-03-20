<?php

namespace Zahzah\LaravelSupport\Supports;

if (config('micro-tenant') !== null){
    class BasePackageManagement extends \Zahzah\MicroTenant\Supports\PackageManagement{

    }
}else{
    class BasePackageManagement{
        
    }
}