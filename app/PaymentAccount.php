<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentAccount extends Model
{
    use SoftDeletes;

    public static function account_types()
    {
        return ['cash' => trans('lang_v1.cash'), 'card' => trans('lang_v1.card'),
            'cheque' => trans('lang_v1.cheque'), 'bank_transfer' => trans('lang_v1.bank_transfer'),
            'payment_gateway' => trans('lang_v1.payment_gateway'), 'other' => trans('lang_v1.other'),
        ];
    }

    public static function account_name($type)
    {
        $types = PaymentAccount::account_types();

        return isset($types[$type]) ? $types[$type] : $type;
    }
    
    public static function forDropdown($business_id, $prepend_none = false, $include_other = false)
    {
        $types = self::account_types();
        if ($prepend_none) {
            $types = ['none' => trans('lang_v1.none')] + $types;
        }
        if (!$include_other) {
            unset($types['other']);
        }
        return $types;
    }
}
