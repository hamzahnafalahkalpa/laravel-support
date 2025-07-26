<?php

namespace Hanafalah\LaravelSupport\Models\Phone;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Hanafalah\LaravelHasProps\Concerns\HasProps;
use Hanafalah\LaravelSupport\Models\BaseModel;

class ModelHasPhone extends BaseModel
{
    use HasUlids, SoftDeletes, HasProps;

    public $incrementing   = false;
    protected $keyType     = "string";
    protected $primaryKey  = "id";
    protected $list        = ['id', 'model_id', 'model_type', 'phone', 'verified_at', 'props'];
    protected $show        = [];

    protected $casts = [
        'phone'       => 'string',
        'verified_at' => 'datetime'
    ];

    public $phone_codes = [
        "+62"   => "Indonesia",
        "+1"    => "Amerika Serikat",
        "+86"   => "Tiongkok",
        "+81"   => "Jepang",
        "+82"   => "Korea Selatan",
        "+60"   => "Malaysia",
        "+65"   => "Singapura",
        "+66"   => "Thailand",
        "+84"   => "Vietnam",
        "+61"   => "Australia",
        "+1"    => "Kanada",
        "+49"   => "Jerman",
        "+33"   => "Perancis",
        "+44"   => "Inggris",
        "+91"   => "India",
        "+55"   => "Brazil",
        "+27"   => "Afrika Selatan",
        "+7"    => "Rusia",
        "+90"   => "Turki",
        "+48"   => "Polandia",
        "+39"   => "Italia",
        "+34"   => "Spanyol",
        "+52"   => "Meksiko",
        "+54"   => "Argentina",
        "+56"   => "Chili",
        "+57"   => "Kolombia",
        "+593"  => "Ekuador",
        "+51"   => "Peru",
        "+598"  => "Uruguay",
        "+58"   => "Venezuela",
        "+32"   => "Belgia",
        "+31"   => "Belanda",
        "+46"   => "Swedia",
        "+47"   => "Norwegia",
        "+45"   => "Denmark",
        "+358"  => "Finlandia",
        "+30"   => "Yunani",
        "+972"  => "Palestina",
        "+20"   => "Mesir",
        "+234"  => "Nigeria",
        "+212"  => "Maroko",
        "+213"  => "Aljazair",
        "+216"  => "Tunisia",
        "+218"  => "Libya",
        "+964"  => "Irak",
        "+98"   => "Iran",
        "+92"   => "Pakistan",
        "+880"  => "Bangladesh",
        "+94"   => "Sri Lanka",
        "+63"   => "Filipina",
        "+886"  => "Taiwan",
        "+852"  => "Hong Kong",
        "+853"  => "Makau",
        "+965"  => "Kuwait",
        "+973"  => "Bahrain",
        "+974"  => "Qatar",
        "+971"  => "Uni Emirat Arab",
        "+968"  => "Oman",
        "+962"  => "Yordania",
        "+963"  => "Suriah",
        "+961"  => "Lebanon",
        "+967"  => "Yaman",
        "+966"  => "Arab Saudi"
    ];

    //EIGER SECTION
    public function model()
    {
        return $this->morphTo();
    }
}
