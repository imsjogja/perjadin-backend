<?php

namespace App\Http\Requests;

use App\Models\Sppd;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSppdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'traveller_nip' => ['required', 'string', 'max:32'],
            'order_giver' => ['required', 'string', 'max:200'],
            'letterhead_type' => ['nullable', 'string', Rule::in([
                Sppd::LETTERHEAD_AGENCY,
                Sppd::LETTERHEAD_SECRETARIAT,
            ])],
            'travel_level' => ['nullable', 'string', 'max:100'],
            'travel_type' => ['nullable', 'string', 'max:100'],
            'departure_date' => ['required', 'date_format:Y-m-d'],
            'return_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:departure_date'],
            'budget_agency' => ['required', 'string', 'max:200'],
            'budget_account' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'issued_place' => ['required', 'string', 'max:150'],
            'issued_date' => ['required', 'date_format:Y-m-d'],
            'followers' => ['nullable', 'array', 'max:20'],
            'followers.*' => ['required', 'string', 'max:32', 'distinct'],
            'signatory' => ['required', 'array'],
            'signatory.nip' => ['required', 'string', 'max:32'],
            'signatory.behalf_of' => ['nullable', 'string', 'max:200'],
            'signatory.signatory_role' => ['nullable', 'string', 'max:200'],
            'signatory.is_acting' => ['nullable', 'boolean'],
        ];
    }
}
