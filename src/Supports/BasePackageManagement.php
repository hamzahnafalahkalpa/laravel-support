<?php

namespace Hanafalah\LaravelSupport\Supports;

if (config('micro-tenant') !== null) {
    class BasePackageManagement extends \Hanafalah\MicroTenant\Supports\PackageManagement {}
} else {
    class BasePackageManagement {}
}
