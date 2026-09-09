<?php

namespace App\Http\Controllers;

use App\Models\FixedBooking;
use App\Services\RecurringBookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FixedBookingController extends Controller
{
    public function review(Request $request, RecurringBookingService $service)
    {
        $data = $request->validate(['preview_token' => ['required', 'uuid'], 'choices' => ['sometimes', 'array'], 'choices.*' => ['required', 'string', 'max:80']]);
        $draft = $this->draft($request, $data['preview_token']);
        // A changed selection must always invalidate the previous confirmation.
        unset($draft['quote'], $draft['confirmation_key']);
        $draft['choices'] = $data['choices'] ?? [];
        $request->session()->put('fixed_booking_draft', $draft);
        try {
            $draft['quote'] = $service->review($draft, $draft['choices']);
            $draft['confirmation_key'] = (string) Str::uuid();
            $request->session()->put('fixed_booking_draft', $draft);
        } catch (ValidationException $e) {
            return redirect()->route('bookings.create-recurring', ['resume' => 1])->withErrors($e->errors());
        }

        return redirect()->route('bookings.create-recurring', ['resume' => 1]);
    }

    public function store(Request $request, RecurringBookingService $service)
    {
        $data = $request->validate(['preview_token' => ['required', 'uuid'], 'confirmation_key' => ['required', 'uuid'], 'confirmed' => ['required', 'accepted']]);
        $draft = $this->draft($request, $data['preview_token']);
        if (! isset($draft['quote'], $draft['confirmation_key']) || ! hash_equals($draft['confirmation_key'], $data['confirmation_key'])) {
            return redirect()->route('bookings.create-recurring', ['resume' => 1])->withErrors(['choices' => 'Vui lòng kiểm tra phương án và số tiền trước khi xác nhận.']);
        }
        try {
            $group = $service->create($request->user()->id, $draft);
        } catch (ValidationException $e) {
            unset($draft['quote'], $draft['confirmation_key']);
            $request->session()->put('fixed_booking_draft', $draft);

            return redirect()->route('bookings.create-recurring', ['resume' => 1])->withErrors($e->errors());
        }

        return redirect()->route('bookings.fixed.show', $group)->with('success', 'Đã giữ chỗ 15 phút. Thanh toán một lần để xác nhận toàn bộ lịch cố định.');
    }

    private function draft(Request $request, string $token): array
    {
        $draft = $request->session()->get('fixed_booking_draft');
        if (! $draft || $draft['user_id'] !== $request->user()->id || ! hash_equals($draft['token'], $token) || $draft['expires_at'] < now()->timestamp) {
            throw ValidationException::withMessages(['preview_token' => 'Bản xem trước đã hết hạn hoặc thay đổi. Vui lòng kiểm tra lịch lại.']);
        }

        return $draft;
    }

    public function show(Request $request, FixedBooking $fixedBooking)
    {
        abort_unless($fixedBooking->user_id === $request->user()->id, 403);
        app(\App\Services\FixedBookingPaymentService::class)->expire($fixedBooking);
        $fixedBooking->refresh();
        $fixedBooking->load(['bookings.bookingDetails.court', 'bookings.bookingDetails.timeSlot', 'bookings.payment']);

        return view('bookings.fixed-show', compact('fixedBooking'));
    }

    public function pay(Request $request, FixedBooking $fixedBooking, \App\Services\VnPayService $gateway)
    {
        abort_unless($fixedBooking->user_id === $request->user()->id, 403);
        $expired = app(\App\Services\FixedBookingPaymentService::class)->expire($fixedBooking);
        $fixedBooking->refresh();
        if ($expired || ! in_array($fixedBooking->status, ['AWAITING_PAYMENT', 'PAYMENT_FAILED'])) {
            return back()->with('error', 'Lịch không còn chờ thanh toán hoặc đã hết hạn giữ chỗ.');
        }
        if ((float) $fixedBooking->total_price === 0.0) {
            app(\App\Services\FixedBookingPaymentService::class)->settle($fixedBooking->payment, true, null, 'FREE');
            return redirect()->route('bookings.fixed.show', $fixedBooking);
        }
        try {
            return redirect()->away($gateway->createPaymentUrl($fixedBooking, route('bookings.vnpay.return')));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function callback(Request $request, bool $ipn = false)
    {
        $data = $request->all();
        $code = '00';
        $success = false;
        $group = null;
        if (! app(\App\Services\VnPayService::class)->verifyResponse($data)) {
            $code = '97';
        } elseif (($data['vnp_TmnCode'] ?? null) !== config('vnpay.tmn_code')) {
            $code = '02';
        } elseif (! preg_match('/^FIX([1-9][0-9]*)$/', $data['vnp_TxnRef'] ?? '', $matches)
            || ! ($group = FixedBooking::with('payment')->find($matches[1])) || ! $group->payment) {
            $code = '01';
        } elseif (! ctype_digit((string) ($data['vnp_Amount'] ?? '')) || (string) $data['vnp_Amount'] !== (string) (int) round((float) $group->payment->amount * 100)) {
            $code = '04';
        } else {
            $gatewaySuccess = ($data['vnp_ResponseCode'] ?? null) === '00' && ($data['vnp_TransactionStatus'] ?? null) === '00';
            $success = app(\App\Services\FixedBookingPaymentService::class)->settle($group->payment,
                $gatewaySuccess,
                $data['vnp_TransactionNo'] ?? null);
            \App\Models\PaymentTransactionLog::firstOrCreate([
                'payment_id' => $group->payment->id,
                'action' => $gatewaySuccess && ! $success ? 'VNPAY_REQUIRES_REVIEW' : 'VNPAY_CALLBACK',
                'note' => 'VNPay '.($data['vnp_TransactionNo'] ?? '').' / '.($data['vnp_ResponseCode'] ?? '').' / '.($data['vnp_TransactionStatus'] ?? ''),
            ], ['amount' => $group->payment->amount, 'new_status' => $group->payment->fresh()->status,
                'metadata' => ['txn_ref' => $data['vnp_TxnRef'], 'gateway_success' => $gatewaySuccess, 'accepted' => $success]]);
            if (! $success && $group->fresh()->status === 'EXPIRED') {
                $code = '02';
            }
        }
        if ($ipn) {
            return response()->json(['RspCode' => $code, 'Message' => $code === '00' ? 'Confirm Success' : 'Payment not accepted']);
        }
        return ($group ? redirect()->route('bookings.fixed.show', $group) : redirect()->route('bookings.index'))
            ->with($success ? 'success' : 'error', $success ? 'Thanh toán thành công. Toàn bộ lịch đã được xác nhận.' : 'Thanh toán chưa được xác nhận. Nếu tài khoản đã bị trừ tiền, vui lòng liên hệ nhân viên để đối chiếu.');
    }
}
