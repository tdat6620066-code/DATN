<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check(); // Must be authenticated
    }

    public function rules(): array
    {
        return [
            'court_id' => 'required|exists:courts,id',
            'booking_date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'time_slot_ids' => 'required|array|min:1',
            'time_slot_ids.*' => 'exists:time_slots,id',
            'voucher_code' => 'nullable|string|max:50',
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
            'time_slot_ids.required' => 'Vui lòng chọn khung giờ',
            'time_slot_ids.array' => 'Khung giờ phải là mảng',
            'time_slot_ids.min' => 'Phải chọn ít nhất 1 khung giờ',
            'time_slot_ids.*.exists' => 'Khung giờ không hợp lệ',
        ];
    }
}
