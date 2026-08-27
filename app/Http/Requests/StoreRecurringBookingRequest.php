<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRecurringBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $lastRecurringDate = now()->addDays(config('booking.max_recurring_days', 365))->toDateString();

        return [
            'court_id' => 'required|exists:courts,id',
            'start_date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date', "before_or_equal:{$lastRecurringDate}"],
            'booking_type' => 'nullable|in:weekly,monthly',
            'days_of_week' => 'required_if:booking_type,weekly|array|min:1',
            'days_of_week.*' => 'integer|between:0,6',
            'days_of_month' => 'required_if:booking_type,monthly|array|min:1',
            'days_of_month.*' => 'integer|between:1,31',
            'time_slot_ids' => 'required_without:time_slot_id|array|min:1',
            'time_slot_ids.*' => 'exists:time_slots,id',
            'time_slot_id' => 'nullable|exists:time_slots,id',
            'voucher_code' => 'nullable|string|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'court_id.required' => 'Vui lòng chọn sân',
            'court_id.exists' => 'Sân không tồn tại',
            'start_date.required' => 'Vui lòng chọn ngày bắt đầu',
            'start_date.date_format' => 'Định dạng ngày không hợp lệ',
            'start_date.after_or_equal' => 'Ngày bắt đầu phải từ hôm nay trở về sau',
            'end_date.required' => 'Vui lòng chọn ngày kết thúc',
            'end_date.date_format' => 'Định dạng ngày không hợp lệ',
            'end_date.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu',
            'days_of_week.required' => 'Vui lòng chọn ngày trong tuần',
            'days_of_week.array' => 'Ngày trong tuần phải là mảng',
            'days_of_week.min' => 'Phải chọn ít nhất 1 ngày',
            'time_slot_id.required' => 'Vui lòng chọn khung giờ',
            'time_slot_id.exists' => 'Khung giờ không hợp lệ',
        ];
    }
}
