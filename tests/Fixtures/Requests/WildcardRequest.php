<?php

namespace Botnetdobbs\Luminous\Tests\Fixtures\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WildcardRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'tag_ids' => ['required', 'array'],
            'tag_ids.*' => ['required', 'uuid'],
            'billing.street' => ['required', 'string'],
            'billing.city' => ['required', 'string'],
        ];
    }
}
