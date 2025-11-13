<?php

namespace Hanafalah\LaravelSupport;

use Hanafalah\LaravelSupport\Contracts;
use Hanafalah\LaravelSupport\LaravelSupport;
use Hanafalah\LaravelSupport\Providers\BaseServiceProvider;
use Illuminate\Database\Eloquent\Builder;

class LaravelSupportServiceProvider extends BaseServiceProvider
{
  public function register()
  {
      $this->registerMainClass(LaravelSupport::class)
      ->registerCommandService(Providers\CommandServiceProvider::class)
      ->registers([
        '*',
        'Migration' => function () {
          return [
            'target' => $this->isMultitenancy() ? '/tenant' : ''
          ];
        },
        'Services' => function () {
          $this->binds([
            Contracts\LaravelSupport::class => function ($app) {
              return new LaravelSupport($app);
            },
            Contracts\FileRepository::class => function ($app) {
              return new FileRepository($app);
            },
            Contracts\Supports\DataManagement::class => Supports\PackageManagement::class
          ]);
        }
      ])
      ->appBooting(function ($app) {
        config([
          'laravel-stub.stub' => config('laravel-support.stub')
        ]);
      });
  }

  public function boot()
  {
    $this->paramSetup();
    Builder::macro('firstOrFailWithMessage', function (string $message) {
      $model = $this->first();
      if (!isset($model)) {
          throw new \Exception($message);
          // throw (new ModelNotFoundException($message))->setModel($this->getModel()::class);
      }
      return $model;
  });
  }

  protected function dir(): string
  {
    return __DIR__ . '/';
  }
}
