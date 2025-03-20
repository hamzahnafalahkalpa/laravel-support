<?php

namespace Hanafalah\LaravelSupport\Concerns\Support;

trait RequestManipulation
{
    use RequestEscaping;

    public function assocRequest(...$args)
    {
        $new = [];
        foreach ($args as $key => $arg) {
            if (\is_numeric($key)) {
                $key = $arg;
                if (request()->has($key)) {
                    $data = [$key => request($key) ?? null];
                    request()->request->remove($key);
                }
            } else {
                $data = [$key => $arg ?? null];
            }
            $new = $this->mergeArray($new, $data ?? []);
        }
        return $new;
    }

    public function moveTo(string $field, array $new_fields): self
    {
        request()->merge([
            $field => $this->assocRequest(...$new_fields)
        ]);
        return $this;
    }
}
