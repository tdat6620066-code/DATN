<?php
namespace App\Http\Controllers;

use App\Models\{Booking, BookingService};
use App\Services\BookingOperationsService;
use Illuminate\Http\Request;

class BookingOperationsController extends Controller
{
    public function checkIn(Request $request, Booking $booking, BookingOperationsService $service)
    {
        return $this->run(fn () => $service->checkIn($booking, $request->user()), 'Đã nhận sân. Giờ kết thúc không thay đổi.');
    }

    public function noShow(Request $request, Booking $booking, BookingOperationsService $service)
    {
        return $this->run(fn () => $service->noShow($booking, $request->user()), 'Đã đánh dấu khách không đến, không tự động hoàn tiền.');
    }

    public function checkout(Request $request, Booking $booking, BookingOperationsService $service)
    {
        $data = $request->validate(['amount' => 'nullable|numeric|min:0|decimal:0,2', 'confirm_services_received' => 'nullable|boolean', 'exception_reason' => 'nullable|string|min:10|max:1000']);
        return $this->run(fn () => $service->checkout($booking, $request->user(), isset($data['amount']) ? (float) $data['amount'] : null,
            $request->boolean('confirm_services_received'), $data['exception_reason'] ?? null), 'Đã hoàn tất trả sân.');
    }

    public function returnRental(Request $request, Booking $booking, BookingService $line, BookingOperationsService $service)
    {
        $data = $request->validate(['returned_quantity' => 'required|integer|min:0']);
        return $this->run(fn () => $service->returnRental($booking, $line, $request->user(), $data['returned_quantity']), 'Đã ghi nhận trả đồ thuê.');
    }

    public function extend(Request $request, Booking $booking, BookingOperationsService $service)
    {
        $data = $request->validate(['time_slot_id' => 'required|integer|exists:time_slots,id', 'amount' => 'required|numeric|min:0|decimal:0,2']);
        try {
            $extension = $service->extend($booking, $request->user(), $data['time_slot_id'], (float) $data['amount']);
            return redirect()->route($request->user()->role === 'ADMIN' ? 'admin.bookings.show' : 'employee.bookings.show', $extension)->with('success', 'Đã thu tiền và giữ khung giờ gia hạn. Đơn gia hạn có mã riêng, nhận sân khi đến giờ.');
        } catch (\DomainException $e) { return back()->with('error', $e->getMessage()); }
        catch (\Exception $e) {
            $errors = json_decode($e->getMessage(), true);
            if (is_array($errors)) return back()->with('error', collect($errors)->pluck('message')->implode(' '));
            throw $e;
        }
    }

    public function lookup(Request $request)
    {
        abort_unless($request->user()->hasPermission('bookings.view'), 403);
        $data = $request->validate(['code' => 'required|string|max:2000']);
        $value = trim($data['code']);
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            $verified = app(\App\Services\QRCodeService::class)->verifyQRCode($value);
            if (! $verified['valid']) return back()->with('error', 'Mã QR không hợp lệ.');
            $booking = $verified['booking'];
        } else $booking = Booking::where('booking_code', $value)->first();
        if (! $booking) return back()->with('error', 'Không tìm thấy đơn đặt sân.');
        return redirect()->route($request->user()->role === 'ADMIN' ? 'admin.bookings.show' : 'employee.bookings.show', $booking);
    }

    private function run(callable $action, string $message)
    {
        try { $action(); return back()->with('success', $message); }
        catch (\DomainException $e) { return back()->with('error', $e->getMessage()); }
    }
}
