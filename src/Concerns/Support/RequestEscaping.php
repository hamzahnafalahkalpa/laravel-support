<?php

namespace Hanafalah\LaravelSupport\Concerns\Support;

use Illuminate\Http\Request;

trait RequestEscaping
{
  protected $__class_request;

  public function requestScope(callable $callable, $request = null)
  {
    $this->__request = (isset($request)) ? $request->all() : request()->all();
    $value = $callable($this);
    $this->newRequest($this->__request);
    $this->setRuquest($this->__request, false);
    return $value;
  }

  /**
   * Creates a new request by adding the provided parameters to the current request.
   *
   * @param array $add The parameters to add to the current request.
   * @return self
   */
  public function newRequest($add = [], $classRequest = null): Request
  {
    if (request()->has('page')) {
      $add = $this->mergeArray($add, [
        'page' => request()->get('page')
      ]);
    }
    $this->__class_request = isset($classRequest) ? $classRequest : Request::class;
    $this->setRuquest($add);
    return $this->__class_request;
  }

  /**
   * Sets the current request by adding the provided parameters.
   *
   * @param array $add The parameters to add to the current request.
   * @param bool $new Whether to create a new request or merge with the existing one.
   * @return self The current instance after setting the request.
   */
  private function setRuquest($add = [], $new = true): self
  {
    if (!$new) $this->__class_request = Request::class;
    $this->requestReplace($this->makeRequest($add));
    return $this;
  }

  /**
   * Creates a new request by merging the provided arguments with the current request.
   *
   * @param array $args The parameters to add to the current ->rurequest.
   * @return array The merged request parameters.
   */
  private function makeRequest($args)
  {
    $request = new $this->__class_request($this->filterArray($args, fn($value) => $value !== null));
    if ($this->__class_request !== Request::class) {
      $request->validate($request->rules(), $request->all());
    }
    $this->__class_request = $request;
    return $request->all();
  }

  /**
   * Replaces the current request with the provided arguments.
   *
   * @param array $args The parameters to replace the current request with.
   * @return self The current instance after replacing the request.
   */
  private function requestReplace(): self
  {
    if (isset($this->__class_request)) request()->replace($this->__class_request->all());
    return $this;
  }
}
