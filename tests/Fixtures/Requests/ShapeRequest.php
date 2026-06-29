<?php

namespace Botnetdobbs\Luminous\Tests\Fixtures\Requests;

use Botnetdobbs\Luminous\Attributes\ApiShape;
use Botnetdobbs\Luminous\Support\Shape;
use Illuminate\Foundation\Http\FormRequest;

#[ApiShape]
class ShapeRequest extends FormRequest
{
    public static function schema(): Shape
    {
        return Shape::object([
            'name' => Shape::string()->description('User name'),
            'amount' => Shape::integer()->min(1)->description('Payment amount in minor units'),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'amount' => ['required', 'integer', 'min:1'],
        ];
    }
}
