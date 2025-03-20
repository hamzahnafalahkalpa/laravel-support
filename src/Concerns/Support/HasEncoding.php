<?php

namespace Zahzah\LaravelSupport\Concerns\Support;

use Carbon\Carbon;

trait HasEncoding {
    protected static $__encoding_schema,$__encoding;

    public static function generateCode($flag): string {
        static::$__encoding = app(config('database.models.Encoding'))->with("modelHasEncoding")->where("flag", $flag)->first();
        if (!isset(static::$__encoding)) return '';

        static::$__encoding_schema = static::$__encoding->modelHasEncoding;
        if (isset(static::$__encoding_schema->structure)){
            $result             = [];
            $separatorConfig = static::$__encoding_schema['separator'] ?? [];
            $separator       = $separatorConfig['separator'] ?? '';
            $distance        = isset($separatorConfig['distance']) ? intval($separatorConfig['distance']) : 0;
            $structure       = static::$__encoding_schema['structure'];
            foreach ($structure as &$part) {
                switch ($part['type']) {
                    case 'alphanumeric':
                        $part['length'] = \strlen($part['value']);
                        $result[] = str_pad($part['value'], $part['length'], ' ', STR_PAD_RIGHT);
                    break;
                    case 'incrementing':
                        if ($part['length'] == 0) $part['length'] = 1;
                        $currentValue  = $part['value'] ?? 0;
                        $currentLength = $part['length'];
                        $currentValue++;
                        $incrementPart = str_pad($currentValue, $currentLength, '0', STR_PAD_LEFT);
                        
                        // UPDATE CURRENT VALUE
                        $part['value'] = $currentValue;
                        $result[]      = $incrementPart;
                        
                        if ($currentValue >= (10 ** $currentLength) - 1) 
                            $part['length']++;
                        
                    break;
                    case 'date':
                        $part['format'] ??= 'YYYY-MM-DD';
                        switch ($part['format']) {
                            case 'YYYY'      : 
                                $part['format'] = 'Y'; 
                                $part['length'] = 4;
                            break;
                            case 'YYYY-MM'   : 
                                $part['format'] = 'Ym'; 
                                $part['length'] = 6;
                            break;
                            case 'YYYY-MM-DD': 
                                $part['format'] = 'Ymd'; 
                                $part['length'] = 8;
                            break;
                            case 'DD-MM-YYYY': 
                                $part['format'] = 'dmY'; 
                                $part['length'] = 8;
                            break;
                            case 'MM-YYYY'   : 
                                $part['format'] = 'mY'; 
                                $part['length'] = 6;
                            break;
                        }                        
                        $dateFormat = $part['format'];
                        $resetable  = $part['resetable'];
                        $result[]   = now()->format($dateFormat);

                        if (isset($resetable)) {
                            static::resetIncrementIfNewPeriod($resetable,$part);
                        }
                    break;
                    default:
                        throw new \InvalidArgumentException("Unknown type: {$part['type']}");
                }
            }
            $finalResult = '';
            foreach ($result as $key => $result_data) {
                if ($distance > 0){
                    if ($key > 0 && $key % $distance == 0) $finalResult .= $separator;
                }
                $finalResult .= $result_data;
            }
            static::$__encoding_schema->value = $finalResult;
            static::$__encoding_schema->setAttribute('structure',$structure);
            static::$__encoding_schema->save();
            // static::$__encoding_schema::$__prop_event_active = false;
            // static::$__encoding_schema->where('id',static::$__encoding_schema->id)
            //         ->update([
            //             'value' => $finalResult,
            //             // 'props' => json_encode([
            //             //     'separator' => $separatorConfig,
            //             //     'structure' => static::$__encoding_schema->structure
            //             // ])
            //         ]);
            return $finalResult;
        }
        return '';
    }


    protected static function resetIncrementIfNewPeriod($resetable,&$part) {
        $patterns = [
            '/(?<!\d)(\d{4})[\/\-_](\d{2})[\/\-_](\d{2})(?!\d)/',
            '/(?<!\d)(\d{4})(\d{2})(\d{2})(?!\d)/',
            '/(?<!\d)(\d{4})[\/\-_](\d{2})(?!\d)/',
            '/(?<!\d)(\d{4})(\d{2})(?!\d)/',
            '/(?<!\d)(\d{4})(?!\d)/',
            '/(?<=\d)[\/\-_](\d{4})[\/\-_](\d{2})[\/\-_](\d{2})(?=\D)/'
        ];

        $date = null;

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, static::$__encoding_schema->value, $matches)) {
                $date = match (true) {
                    isset($matches[3]) => "{$matches[1]}{$matches[2]}{$matches[3]}",
                    isset($matches[2]) => "{$matches[1]}{$matches[2]}",
                    default            => $matches[1],
                };
                break;
            }
        }

        $notReset = false;
        if (isset($date) && isset(static::$__encoding_schema->value)) {
            $currentDate          = now();
            $currentFormattedDate = Carbon::parse($date);

            switch ($resetable) {
                case 'year'  : $notReset = !($currentDate->year !== $currentFormattedDate->year);break;
                case 'month' : $notReset = !($currentDate->year !== $currentFormattedDate->year || $currentDate->month !== $currentFormattedDate->month);break;
                case 'day'   : $notReset = !(!$currentDate->isSameDay($currentFormattedDate));break;
            }
        }

        if (!$notReset && $part['type'] === 'incrementing') 
            $part['value'] = str_pad('0', $part['length'], '0', STR_PAD_LEFT);
    }

    //EIGER SECTION

    public function modelHasEncoding(){return $this->morphOneModel('ModelHasEncoding','reference');}
    public function modelHasEncodings(){return $this->morphManyModel('ModelHasEncoding','reference');}
    
    public function encoding(){
        $encoding = $this->EncodingModel();
        return $this->hasOneThroughModel(
            'Encoding', 'ModelHasEncoding',
            'reference_id', $encoding->getKeyName(),
            $this->getKeyName(),
            $encoding->getForeignKey()
        )->where('reference_type',$this->getMorphClass());
    }

    public function encodings(){
        return $this->belongsToManyModel(
            'Encoding','ModelHasEncoding',
            'reference_id',$this->EncodingModel()->getForeignKey()
        )->where('reference_type',$this->getMorphClass());
    }
}
