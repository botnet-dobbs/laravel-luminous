<?php

namespace Botnetdobbs\Luminous\Tests\Fixtures\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmedRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'password' => ['required', 'nullable', 'string', 'confirmed'],
            'backup_code' => ['sometimes', 'string', 'confirmed'],
        ];
    }
}
