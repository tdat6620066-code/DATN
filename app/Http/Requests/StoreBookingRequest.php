<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\TimeSlot;

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
            'time_slot_ids.*' => 'integer|exists:time_slots,id|distinct',
            'voucher_code' => 'nullable|string|max:50',
            'daily_duration_confirmed' => 'sometimes|accepted',
            'services' => 'nullable|array|max:100',
            'services.*.service_item_id' => 'required|integer|distinct|exists:service_items,id',
            'services.*.quantity' => 'required|integer|min:0|max:1000',
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

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isEmpty() && ! TimeSlot::areConsecutive(TimeSlot::whereIn('id', $this->input('time_slot_ids'))->get())) {
                $validator->errors()->add('time_slot_ids', 'Chỉ được chọn các khung giờ liền nhau, không cách quãng hoặc chồng lấn.');
            }
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $duration = app(\App\Services\DailyBookingDurationService::class);
            $minutes = $duration->totalMinutes($this->user()->id, $this->input('booking_date'), $this->input('time_slot_ids'));
            if ($minutes >= config('booking.daily_confirmation_minutes', 240) && ! $this->boolean('daily_duration_confirmed')) {
                $validator->errors()->add('daily_duration_confirmed', $duration->warning($minutes, $this->input('booking_date')));
            }
        });
    }
}
