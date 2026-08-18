<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'court_id' => 'required|exists:courts,id',
            'booking_date' => 'required|date_format:Y-m-d|after_or_equal:today',
        ];
    }

    public function messages(): array
    {
        return [
            'court_id.required' => 'Vui lòng chọn sân',
            'court_id.exists' => 'Sân không tồn tại',
            'booking_date.required' => 'Vui lòng chọn ngày',
            'booking_date.date_format' => 'Định dạng ngày không hợp lệ',
            'booking_date.after_or_equal' => 'Ngày phải từ hôm nay trở về sau',
        ];
    }
}
