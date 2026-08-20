<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSptAssigneesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nips' => ['required', 'array', 'min:1', 'max:20'],
            'nips.*' => ['required', 'string', 'max:32', 'distinct'],
        ];
    }
}
