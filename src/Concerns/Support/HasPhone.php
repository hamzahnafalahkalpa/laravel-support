<?php

namespace Zahzah\LaravelSupport\Concerns\Support;
use Illuminate\Support\Str;
trait HasPhone {
    public function setPhone(mixed $phones){
        $phones = $this->mustArray($phones);
        $phone_values = [];
        foreach ($phones as $phone) {
            $phone = Str::replace("+62", "08", $phone);
            $phone = preg_replace('/\s*[- ]\s*/', '', $phone);
            $phone_values[] = $phone;
            $this->hasPhone()->firstOrCreate([
                'phone' => $phone
            ]);
        }
        $this->hasPhone()->whereNotIn('phone',$phone_values)->delete();
    }

    public function hasPhone(){
        return $this->morphOneModel('ModelHasPhone','model');
    }


    public function hasPhones(){
        return $this->morphManyModel('ModelHasPhone','model');
    }
}