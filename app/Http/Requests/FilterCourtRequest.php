<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FilterCourtRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'keyword' => 'nullable|string|max:255',
            'court_type_id' => 'nullable|exists:court_types,id',
            'amenity_ids' => 'nullable|array',
            'amenity_ids.*' => 'exists:amenities,id',
            'price_min' => 'nullable|numeric|min:0',
            'price_max' => 'nullable|numeric|min:0',
            'booking_date' => 'nullable|date_format:Y-m-d',
            'time_slot_id' => 'nullable|exists:time_slots,id',
            'sort_by' => 'nullable|in:price_asc,price_desc,name_asc,name_desc,most_booked',
            'page' => 'nullable|integer|min:1',
        ];
    }
}
