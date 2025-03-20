<?php

namespace Zahzah\LaravelSupport\Concerns\Support;

trait HasGoogleTranslate {
    protected $__translate;

    public function initTranslation(){
        $this->__translate = new \Stichoza\GoogleTranslate\GoogleTranslate();
        $this->__translate->setSource(config('laravel-support.translate.from'))
                          ->setTarget(config('laravel-support.translate.to'));
    }

    public function translate(string $text){
        if (!isset($this->__translate)) $this->initTranslation();
        return $this->__translate->translate($text);
    }
}