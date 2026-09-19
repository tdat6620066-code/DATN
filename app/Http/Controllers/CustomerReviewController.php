<?php

namespace App\Http\Controllers;

use App\Models\{Booking, Review};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerReviewController extends Controller
{
    public function store(Request $request, Booking $booking)
    {
        abort_unless($booking->user_id === $request->user()->id, 403);
        $data = $request->validate([
            'court_id' => ['required', 'integer'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'content' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($booking, $request, $data) {
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'COMPLETED', 403, 'Chỉ có thể đánh giá booking đã hoàn thành.');
            abort_unless($locked->bookingDetails()->where('court_id', $data['court_id'])->where('status', '!=', 'CANCELLED')->exists(), 403);
            if (Review::where('booking_id', $locked->id)->where('court_id', $data['court_id'])->exists()) {
                throw ValidationException::withMessages(['review' => 'Bạn đã đánh giá sân này trong booking này.']);
            }
            Review::create($data + ['user_id' => $request->user()->id, 'booking_id' => $locked->id, 'status' => 'PENDING']);
        });

        return redirect()->route('bookings.show', $booking)->with('success', 'Đã gửi đánh giá. Nội dung sẽ hiển thị công khai sau khi được duyệt.');
    }
}
