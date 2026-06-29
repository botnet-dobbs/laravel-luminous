<?php

namespace Botnetdobbs\Luminous\Tests\Fixtures\Requests;

use Botnetdobbs\Luminous\Support\Shape;
use Illuminate\Foundation\Http\FormRequest;

class HintsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1', 'max:1000000'],
            'description' => ['required', 'string', 'max:500'],
            'currency' => ['required', 'string', 'size:3'],
        ];
    }

    public function hints(): array
    {
        return [
            'amount' => Shape::integer()->description('Amount in minor currency units')->example(10000),
            'description' => Shape::string()->description('Human-readable payment description'),
            // currency has no hint. rules() is sufficient
        ];
    }
}
