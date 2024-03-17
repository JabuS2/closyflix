<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Str;

class PPVMinMax implements Rule
{

    protected $isPPVStream = false;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($isPPVStream = false)
    {
        $this->isPPVStream = $isPPVStream;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $min_ppv_content_price = getSetting('payments.min_ppv_content_price') ? (int)getSetting('payments.min_ppv_content_price') : 1;
        $max_ppv_content_price = getSetting('payments.max_ppv_content_price') ? (int)getSetting('payments.max_ppv_content_price') : 500;
        $hasError = false;

        if($this->isPPVStream){
            if(getSetting('streams.allow_free_streams')){
                if((int)$value < $min_ppv_content_price && (int)$value != 0){
                    $hasError = true;
                }
            }
            else{
                if((int)$value < $min_ppv_content_price){
                    $hasError = true;
                }
            }
        }
        else{
            if((int)$value < $min_ppv_content_price && (int)$value != 0){
                $hasError = true;
            }
        }

        if((int)$value > $max_ppv_content_price){
            $hasError = true;
        }
        return !$hasError;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return __('The price must be between :min and :max.',['min' => getSetting('payments.min_ppv_content_price') ?? 1, 'max' => getSetting('payments.max_ppv_content_price') ?? 500]);
    }
}
