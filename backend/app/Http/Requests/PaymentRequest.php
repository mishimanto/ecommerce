<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'order_id' => 'required|exists:orders,id',
            'payment_method' => 'required|in:stripe,sslcommerz,cod',
            'card_number' => 'required_if:payment_method,stripe|string',
            'expiry_month' => 'required_if:payment_method,stripe|integer|between:1,12',
            'expiry_year' => 'required_if:payment_method,stripe|integer|min:' . date('Y'),
            'cvc' => 'required_if:payment_method,stripe|string|min:3|max:4'
        ];
    }
}