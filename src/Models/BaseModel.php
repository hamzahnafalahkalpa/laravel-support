<?php

namespace Hanafalah\LaravelSupport\Models;

if (config('micro-tenant') !== null) {
    class BaseModel extends \Hanafalah\MicroTenant\Models\BaseModel
    {
        public static $__activity;

        protected static function booted(): void
        {
            parent::booted();
        }
    }
} else {
    class BaseModel extends SupportBaseModel
    {
        public static $__activity;

        /**
         * The "booted" method of the model.
         *
         * @return void
         */
        protected static function booted(): void
        {
            parent::booted();
        }
    }
}
