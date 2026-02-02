<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'shipping_address_id' => 'required|exists:addresses,id',
            'billing_address_id' => 'required|exists:addresses,id',
            'shipping_method' => 'required|string',
            'shipping_cost' => 'required|numeric|min:0',
            'payment_method' => 'required|in:stripe,sslcommerz,cod,bank',
            'notes' => 'nullable|string|max:500',
            'coupon_code' => 'nullable|string|exists:coupons,code',
            'agree_terms' => 'required|accepted'
        ];
    }

    public function messages()
    {
        return [
            'shipping_address_id.required' => 'Shipping address is required',
            'billing_address_id.required' => 'Billing address is required',
            'agree_terms.required' => 'You must agree to the terms and conditions'
        ];
    }
}