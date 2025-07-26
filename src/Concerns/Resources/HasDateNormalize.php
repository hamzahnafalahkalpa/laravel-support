<?php

namespace Hanafalah\LaravelSupport\Concerns\Resources;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Hanafalah\LaravelSupport\Concerns\Support\HasArray;

trait HasDateNormalize
{
    use HasArray;

    private $keyy;

    public function normalize()
    {
        if (config('app.client_timezone') !== null) {
            $dates = $this->filterDates();
            foreach ($dates as $key => $cast) {
                if (Str::contains($cast, 'props->')) {
                    $this->dateAsProp($cast);
                } else {
                    if (isset($this->{$key})) $this->{$key} = $this->dateAsCarbon($this->{$key});
                }
            }
            if (isset($this->prop_activity)) $this->remaskingActivity();
        }
    }

    private function accessDataRecursive(&$source, array $pathParts): void
    {
        if (empty($pathParts)) {
            $this->generateDateFromFormat($source);
        } else {
            $part = array_shift($pathParts);

            if (\is_object($source)) {
                $source_part = &$source->{$part};
            } elseif (\is_array($source)) {
                $source_part = &$source[$part];
            }else{
                $source_part = null;
            }
            if (isset($source_part)) {
                $this->accessDataRecursive($source_part, $pathParts);
            }
        }
    }

    private function filterDates()
    {
        $dates = array_filter($this->getCasts(), function ($cast) {
            return in_array($cast, ['date', 'datetime']);
        });
        $propsQuery = [];
        if (\method_exists($this->resource, 'getPropsQuery')) {
            $propsQuery = $this->resource->getPropsQuery();
            $propsQuery = array_intersect_key($propsQuery, $dates);
            $dates      = $this->mergeArray($dates, $propsQuery);
        }
        return $dates;
    }

    private function remaskingActivity()
    {
        $prop_activity = array_merge([], $this->prop_activity);
        foreach ($prop_activity as $key => &$value) {
            if ($this->isAssociative($value)) {
                foreach ($value as &$v) {
                    if (isset($v['at'])) {
                        if ($this->isValidateDate($v['at'])) {
                            $this->generateDateFromFormat($v['at']);
                        }
                    }
                }
            } else {
                foreach ($value as &$v) {
                    foreach ($v as &$vv) {
                        if (isset($vv['at'])) {
                            if ($this->isValidateDate($vv['at'])) {
                                $this->generateDateFromFormat($vv['at']);
                            }
                        }
                    }
                }
            }
        }

        $this->resource->setAttribute('prop_activity', $prop_activity);
        // $this->prop_activity = $prop_activity;
    }

    private function dateAsCarbon($date)
    {
        if ($date instanceof Carbon) {
            $date = $date->setTimezone(config('app.client_timezone'))
                ->format('Y-m-d H:i:s');
        }
        return $date;
    }

    private function dateAsProp(string $cast): void
    {
        $cast      = Str::replace('props->', '', $cast);
        $pathParts = explode('->', $cast);
        $part      = $pathParts[0];
        $source    = $this->resource->{$part};
        \array_shift($pathParts);
        $this->accessDataRecursive($source, $pathParts);
        $this->resource->setAttribute($part, $source);
    }

    private function generateDateFromFormat(mixed &$date): void
    {
        $date = Carbon::createFromFormat('Y-m-d H:i:s', $date)->setTimezone(config('app.client_timezone'))->format('Y-m-d H:i:s');
    }

    private function isValidateDate($date): bool
    {
        return isset($date) && $this->isPregMatch($date);
    }

    private function isPregMatch(mixed $date): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $date);
    }
}
