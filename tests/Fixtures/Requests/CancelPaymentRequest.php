<?php

namespace Botnetdobbs\Luminous\Tests\Fixtures\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelPaymentRequest extends FormRequest
{
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:500']];
    }
}
