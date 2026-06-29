<?php

namespace Botnetdobbs\Luminous\Tests\Fixtures\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FileUploadRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'avatar' => ['required', 'mimetypes:image/jpeg,image/png'],
        ];
    }
}
