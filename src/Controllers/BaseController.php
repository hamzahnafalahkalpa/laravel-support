<?php

namespace Zahzah\LaravelSupport\Controllers;

use Illuminate\Routing\Controller;
use Zahzah\LaravelSupport\Concerns\DatabaseConfiguration\HasModelConfiguration;
use Zahzah\LaravelSupport\Concerns\ServiceProvider\HasConfiguration;
use Zahzah\LaravelSupport\Concerns\Support\{
    HasArray, HasCall,
    HasRequest, HasResponse
};

use function Aws\is_associative;

class BaseController extends Controller{
    use HasRequest, HasResponse,
        HasConfiguration,
        HasModelConfiguration,
        HasCall,
        HasArray;
    
    public function __construct(){
        $this->initConfig();
        $this->paramSetup();
    }

    public function toProps(array $fields = []): self{
        $prop_fields = [];
        foreach ($fields as $key => $field) {
            if (!is_numeric($key)){
                $prop_fields[$key] = $field;
            }else{
                $prop_fields[$field] = request()->{$field};
            }
        }
        request()->merge(['props' => $prop_fields]);
        return $this;
    }

    public function callCustomMethod(): array{
        return ['Model','Configuration'];
    }
}