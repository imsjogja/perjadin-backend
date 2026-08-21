<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'unit_id' => ['required', 'uuid'],
            'dasar' => ['required', 'array', 'min:1', 'max:20'],
            'dasar.*' => ['required', 'string', 'max:5000', 'distinct'],
            'disposisi' => ['nullable', 'string', 'max:5000'],
            'dalam_rangka' => ['required', 'string', 'max:2000'],
            'issued_place' => ['required', 'string', 'max:150'],
            'issued_date' => ['required', 'date_format:Y-m-d'],
            'destination' => ['required', 'array'],
            'destination.transportation' => ['required', 'string', 'max:100'],
            'destination.departure_place' => ['required', 'string', 'max:150'],
            'destination.destination_place' => ['required', 'string', 'max:150'],
            'destination.duration_days' => ['required', 'integer', 'min:1', 'max:365'],
            'signatory' => ['required', 'array'],
            'signatory.nip' => ['required', 'string', 'max:32'],
            'signatory.behalf_of' => ['nullable', 'string', 'max:200'],
            'signatory.signatory_role' => ['nullable', 'string', 'max:200'],
            'signatory.is_acting' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('dasar'))) {
            $this->merge([
                'dasar' => [$this->input('dasar')],
            ]);
        }
    }
}
