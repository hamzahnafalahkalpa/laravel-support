<?php

namespace Hanafalah\LaravelSupport\Data;

use Hanafalah\LaravelSupport\Contracts\Data\TimezoneData as DataTimezoneData;

class TimezoneData extends UnicodeData implements DataTimezoneData
{
    public static function before(array &$attributes){
        $attributes['flag'] ??= 'Timezone';
        parent::before($attributes);
    }
}