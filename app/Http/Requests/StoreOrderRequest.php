<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'segment_id' => ['required', 'integer', 'exists:segments,id'], 'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'customer' => ['required', 'array:name,phone,email,company'],
            'customer.name' => ['required', 'string', 'min:2', 'max:150'],
            'customer.phone' => ['required', 'regex:/^\+?[1-9][0-9]{7,14}$/'],
            'customer.email' => ['nullable', 'email', 'max:254'], 'customer.company' => ['nullable', 'string', 'max:150'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*' => ['array:product_id,service_id,quantity,customization'],
            'items.*.product_id' => ['nullable', 'integer', 'distinct'], 'items.*.service_id' => ['nullable', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:9999'],
            'items.*.customization' => ['nullable', 'array:instructions'], 'items.*.customization.instructions' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'], 'idempotency_key' => ['sometimes', 'uuid'],
        ];
    }
}
