<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookingRequest;
use App\Http\Requests\StoreRecurringBookingRequest;
use App\Models\Booking;
use App\Models\Court;
use App\Models\TimeSlot;
use App\Services\BookingService;
use App\Services\CourtAvailabilityService;
use App\Services\PaymentService;
use App\Services\QRCodeService;
use App\Services\VnPayService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookingController extends Controller
{
    private BookingService $bookingService;

    private PaymentService $paymentService;

    private QRCodeService $qrService;

    private VnPayService $vnpayService;

    public function __construct(
        BookingService $bookingService,
        PaymentService $paymentService,
        QRCodeService $qrService,
        VnPayService $vnpayService
    ) {
        $this->bookingService = $bookingService;
        $this->paymentService = $paymentService;
        $this->qrService = $qrService;
        $this->vnpayService = $vnpayService;
    }

    /**
     * Display user's bookings
     */
    public function index(Request $request)
    {
        $bookings = Booking::where('bookings.user_id', Auth::id())
            ->where(fn ($q) => $q->whereNull('bookings.fixed_booking_id')->orWhereIn('bookings.id',
                Booking::selectRaw('MAX(id)')->whereNotNull('fixed_booking_id')->groupBy('fixed_booking_id')))
            ->leftJoin('fixed_bookings', 'fixed_bookings.id', '=', 'bookings.fixed_booking_id')
            ->select('bookings.*')
            ->with('bookingDetails.court', 'bookingDetails.timeSlot', 'payment', 'fixedBooking.bookings')
            ->orderByRaw('COALESCE(fixed_bookings.created_at, bookings.created_at) DESC')
            ->orderByDesc('bookings.id')
            ->paginate(15);

        return view('bookings.index', ['bookings' => $bookings]);
    }

    /**
     * UC18, UC19, UC20 - Create booking form and handle submission
     */
    public function create(Request $request)
    {
        $courts = Court::where('status', 'ACTIVE')
            ->with('courtType', 'images', 'prices')
            ->get();

        $timeSlots = TimeSlot::where('status', 'ACTIVE')
            ->orderBy('start_time')
            ->get();

        // Get booking date from request or default to today
        $bookingDate = $request->has('booking_date')
            ? Carbon::parse($request->booking_date)
            : Carbon::today();

        $selectedCourtId = $request->integer('court_id');
        $selectedCourt = $courts->firstWhere('id', $selectedCourtId);
        $selectedTimeSlotId = $request->integer('time_slot_id');
        if (! $timeSlots->contains('id', $selectedTimeSlotId)) {
            $selectedTimeSlotId = null;
        }

        // Generate date range for calendar (show 30 days starting from today)
        $dateRange = collect();
        for ($i = 0; $i < 30; $i++) {
            $dateRange->push(Carbon::today()->addDays($i));
        }

        // Prepare availability data by court and time slot
        $availabilityData = [];
        if ($courts->count() > 0) {
            $firstCourt = $courts->first();
            foreach ($courts as $court) {
                $availabilityData[$court->id] = [];
                foreach ($timeSlots as $slot) {
                    $status = app(CourtAvailabilityService::class)
                        ->checkAvailability($court->id, $bookingDate, $slot->id);

                    $availabilityData[$court->id][$slot->id] = [
                        'status' => $status,
                        'price' => $court->prices()
                            ->where('time_slot_id', $slot->id)
                            ->where('status', 'ACTIVE')
                            ->where('effective_from', '<=', $bookingDate->toDateString())
                            ->where(function ($q) use ($bookingDate) {
                                $q->whereNull('effective_to')
                                    ->orWhere('effective_to', '>=', $bookingDate->toDateString());
                            })
                            ->first()?->price ?? 0,
                    ];
                }
            }
        }

        return view('bookings.create', [
            'courts' => $courts,
            'timeSlots' => $timeSlots,
            'bookingDate' => $bookingDate,
            'dateRange' => $dateRange,
            'availabilityData' => $availabilityData,
            'selectedCourt' => $selectedCourt,
            'selectedTimeSlotId' => $selectedTimeSlotId,
        ]);
    }

    /**
     * UC18, UC19, UC20 - Store booking
     */
    public function store(StoreBookingRequest $request)
    {
        try {
            // Build booking details from request
            $bookingDetails = [];
            foreach ($request->time_slot_ids as $timeSlotId) {
                $bookingDetails[] = [
                    'court_id' => $request->court_id,
                    'booking_date' => $request->booking_date,
                    'time_slot_id' => $timeSlotId,
                ];
            }

            // Create booking with transaction and locking
            $booking = $this->bookingService->createBooking(
                Auth::id(), $bookingDetails, $request->voucher_code,
                services: $request->validated('services', []) ?? []
            );

            return redirect()
                ->route('bookings.show', $booking)
                ->with('success', 'Đặt sân thành công. Vui lòng hoàn tất thanh toán.');

        } catch (\Exception $e) {
            $errors = json_decode($e->getMessage(), true);

            if (is_array($errors)) {
                return back()
                    ->with('booking_errors', $errors)
                    ->withInput();
            }

            return back()
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }

    /**
     * UC18 - Show booking details and payment form
     */
    public function show(Booking $booking)
    {
        // Authorize: user can only see their own bookings
        $this->authorize('view', $booking);
        if ($booking->status === 'PENDING_PAYMENT' && $booking->fixedBooking && $booking->fixedBooking->status !== 'LEGACY') {
            return redirect()->route('bookings.fixed.show', $booking->fixedBooking);
        }

        if ($this->expireHoldIfNeeded($booking)) {
            return redirect()->route('bookings.show', $booking)
                ->with('error', 'Thời gian giữ chỗ 5 phút đã hết. Khung giờ đã được giải phóng.');
        }

        $booking->load('bookingDetails.court', 'bookingDetails.timeSlot', 'payment');

        return view('bookings.show', ['booking' => $booking]);
    }

    /**
     * Hiển thị mã QR booking để khách hàng check-in.
     * QR chỉ hợp lệ với booking hợp lệ và chưa hoàn thành/hủy.
     */
    public function showQr(Booking $booking)
    {
        $this->authorize('view', $booking);

        $booking->load('bookingDetails.court', 'bookingDetails.timeSlot', 'user');

        $this->expireHoldIfNeeded($booking);

        $qrCode = $this->qrService->generateQRCode($booking);

        return view('bookings.qr', [
            'booking' => $booking,
            'qr_code' => $qrCode,
        ]);
    }

    /**
     * UC21 - Create recurring booking form
     */
    public function scanQr(Booking $booking)
    {
        $this->expireHoldIfNeeded($booking);
        $booking->load('bookingDetails.court', 'bookingDetails.timeSlot', 'services.item');
        return response()->view('bookings.qr-info', compact('booking'))
            ->header('Cache-Control', 'private, no-store')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function createRecurring(Request $request)
    {
        $draft = $request->boolean('resume') ? $request->session()->get('fixed_booking_draft') : null;
        if ($draft && ($draft['user_id'] !== $request->user()->id || $draft['expires_at'] < now()->timestamp)) {
            $draft = null;
        }
        if ($draft) {
            $request->merge($draft['definition']);
        }
        $courtId = $request->integer('court_id');
        $selectedCourt = $courtId
            ? Court::where('status', 'ACTIVE')->with('courtType')->findOrFail($courtId)
            : null;

        $courts = Court::where('status', 'ACTIVE')
            ->with('courtType', 'images', 'prices')
            ->get();

        $timeSlots = TimeSlot::where('status', 'ACTIVE')->get();

        return view('bookings.create-recurring', [
            'courts' => $courts,
            'selectedCourt' => $selectedCourt,
            'timeSlots' => $timeSlots,
            'bookingType' => $request->input('booking_type', 'weekly'),
            'preview' => $draft['preview'] ?? null,
            'draft' => $draft,
        ]);
    }

    /**
     * UC21 - Generate a recurring schedule without creating a booking.
     */
    public function previewRecurring(StoreRecurringBookingRequest $request)
    {
        $data = $request->validated();
        $preview = app(\App\Services\RecurringBookingService::class)->preview($data);
        $request->session()->put('fixed_booking_draft', [
            'token' => (string) \Illuminate\Support\Str::uuid(), 'user_id' => $request->user()->id,
            'expires_at' => now()->addMinutes(20)->timestamp, 'definition' => $data, 'preview' => $preview,
        ]);

        $courts = Court::where('status', 'ACTIVE')->with('courtType')->get();
        $timeSlots = TimeSlot::where('status', 'ACTIVE')->get();
        $selectedCourt = Court::where('status', 'ACTIVE')->with('courtType')->find($data['court_id']);

        if ($request->boolean('from_court')) {
            return redirect()->route('courts.show', $data['court_id'])
                ->with('recurring_preview', $preview)
                ->withInput();
        }

        return redirect()->route('bookings.create-recurring', ['resume' => 1]);
    }

    /**
     * Tạo URL thanh toán VNPay và chuyển hướng user sang trang thanh toán.
     */
    public function vnpayCreate(Booking $booking)
    {
        $this->authorize('confirmPayment', $booking);
        if ($booking->fixedBooking && $booking->fixedBooking->status !== 'LEGACY') {
            return redirect()->route('bookings.fixed.show', $booking->fixedBooking);
        }

        if ($booking->status !== 'PENDING_PAYMENT') {
            return back()->with('error', 'Booking này không thể thanh toán.');
        }

        if ($this->expireHoldIfNeeded($booking)) {
            return redirect()->route('bookings.show', $booking)
                ->with('error', 'Thời gian giữ chỗ đã hết. Vui lòng chọn lại khung giờ.');
        }

        try {
            $returnUrl = route('bookings.vnpay.return');
            $paymentUrl = $this->vnpayService->createPaymentUrl($booking, $returnUrl);
        } catch (\RuntimeException $e) {
            report($e);

            return back()->with('error', $e->getMessage());
        }

        return redirect()->away($paymentUrl);
    }

    /** Save the owner's note from the checkout screen before payment. */
    public function updateNote(Booking $booking, Request $request)
    {
        $this->authorize('confirmPayment', $booking);

        if ($booking->status !== 'PENDING_PAYMENT') {
            return back()->with('error', 'Đơn đặt sân này không thể cập nhật ghi chú.');
        }

        if ($this->expireHoldIfNeeded($booking)) {
            return redirect()->route('bookings.show', $booking)
                ->with('error', 'Thời gian giữ chỗ đã hết. Vui lòng chọn lại khung giờ.');
        }

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $booking->update(['note' => $validated['note'] ?? null]);

        return redirect()->route('bookings.vnpay', $booking);
    }

    /** Immediately expire a stale hold before allowing a payment action. */
    private function expireHoldIfNeeded(Booking $booking): bool
    {
        if ($booking->fixedBooking && $booking->fixedBooking->status !== 'LEGACY') {
            if ($booking->status !== 'PENDING_PAYMENT') return false;
            return app(\App\Services\FixedBookingPaymentService::class)->expire($booking->fixedBooking);
        }
        if ($booking->status !== 'PENDING_PAYMENT' || ! $booking->isHoldExpired()) {
            return false;
        }

        $booking->loadMissing('payment');
        $booking->update(['status' => 'EXPIRED']);
        $booking->bookingDetails()->update(['status' => 'CANCELLED']);
        $booking->payment?->update(['status' => 'FAILED']);

        return true;
    }

    /** VNPay redirects the customer here after payment. */
    public function vnpayReturn(Request $request)
    {
        if (str_starts_with((string) $request->input('vnp_TxnRef'), 'SVC')) return app(ServiceOrderController::class)->callback($request);
        if (str_starts_with((string) $request->input('vnp_TxnRef'), 'FIX')) {
            return app(FixedBookingController::class)->callback($request);
        }
        $data = $request->all();

        if (! $this->vnpayService->verifyResponse($data)) {
            return redirect()->route('bookings.index')
                ->with('error', 'Chữ ký thanh toán VNPay không hợp lệ.');
        }

        $booking = $this->resolveBookingFromTxnRef($data['vnp_TxnRef'] ?? null);

        if (! $booking) {
            return redirect()->route('bookings.index')
                ->with('error', 'Không tìm thấy đơn đặt sân tương ứng.');
        }

        if (! $this->isSuccessfulVnpayPayment($booking, $data)) {
            $this->releaseFailedVnpayHold($booking, $data);
            return redirect()->route('bookings.show', $booking)
                ->with('error', 'Giao dịch không thành công hoặc thông tin thanh toán không hợp lệ.');
        }

        if ($booking->payment && !in_array($booking->payment->status, ['PAID', 'REFUNDED', 'PARTIALLY_REFUNDED'], true)) {
            try {
                $this->paymentService->markAsPaid(
                    $booking->payment,
                    $data['vnp_TransactionNo'] ?? $data['vnp_TxnRef'],
                    'vnpay'
                );
            } catch (\DomainException $e) {
                return redirect()->route('bookings.show', $booking)->with('error', $e->getMessage());
            }
        }

        return redirect()->route('bookings.show', $booking)
            ->with('success', 'Thanh toán VNPay thành công. Đơn đặt sân đã được xác nhận.');
    }

    /**
     * IPN URL: VNPay gọi trực tiếp để xác nhận kết quả giao dịch.
     */
    public function vnpayIpn(Request $request)
    {
        if (str_starts_with((string) $request->input('vnp_TxnRef'), 'SVC')) return app(ServiceOrderController::class)->callback($request, true);
        if (str_starts_with((string) $request->input('vnp_TxnRef'), 'FIX')) {
            return app(FixedBookingController::class)->callback($request, true);
        }
        $data = $request->all();

        if (! $this->vnpayService->verifyResponse($data)) {
            return response()->json(['RspCode' => '97', 'Message' => 'Invalid Checksum']);
        }

        if (($data['vnp_TmnCode'] ?? null) !== config('vnpay.tmn_code')) {
            return response()->json(['RspCode' => '02', 'Message' => 'Invalid merchant']);
        }

        $booking = $this->resolveBookingFromTxnRef($data['vnp_TxnRef'] ?? null);

        if (! $booking) {
            return response()->json(['RspCode' => '01', 'Message' => 'Order not found']);
        }

        if ((int) ($data['vnp_Amount'] ?? 0) !== (int) round((float) $booking->total_amount * 100)) {
            return response()->json(['RspCode' => '04', 'Message' => 'Invalid amount']);
        }

        if (! $this->isSuccessfulVnpayPayment($booking, $data)) {
            $this->releaseFailedVnpayHold($booking, $data);
            return response()->json(['RspCode' => '00', 'Message' => 'Confirm Success']);
        }

        if ($booking->payment && !in_array($booking->payment->status, ['PAID', 'REFUNDED', 'PARTIALLY_REFUNDED'], true)) {
            try {
                $this->paymentService->markAsPaid(
                    $booking->payment,
                    $data['vnp_TransactionNo'] ?? $data['vnp_TxnRef'],
                    'vnpay'
                );
            } catch (\DomainException $e) {
                return response()->json(['RspCode' => '02', 'Message' => 'Booking unavailable']);
            }
        }

        return response()->json(['RspCode' => '00', 'Message' => 'Confirm Success']);
    }

    private function releaseFailedVnpayHold(Booking $booking, array $data): void
    {
        // Both callers have verified the signature. Reject mismatched or incomplete receipts.
        if (($data['vnp_TmnCode'] ?? null) === config('vnpay.tmn_code')
            && (int) ($data['vnp_Amount'] ?? 0) === (int) round((float) $booking->total_amount * 100)
            && isset($data['vnp_ResponseCode'], $data['vnp_TransactionStatus'])
            && ($data['vnp_ResponseCode'] !== '00' || $data['vnp_TransactionStatus'] !== '00')
            && $booking->payment) {
            $this->paymentService->markAsFailed($booking->payment, $data['vnp_TransactionNo'] ?? null);
        }
    }

    private function isSuccessfulVnpayPayment(Booking $booking, array $data): bool
    {
        return ($data['vnp_TmnCode'] ?? null) === config('vnpay.tmn_code')
            && ($data['vnp_ResponseCode'] ?? null) === '00'
            && ($data['vnp_TransactionStatus'] ?? null) === '00'
            && (int) ($data['vnp_Amount'] ?? 0) === (int) round((float) $booking->total_amount * 100);
    }

    /** Resolve {booking_id}{YmdHis} from the VNPay transaction reference. */
    private function resolveBookingFromTxnRef(?string $txnRef): ?Booking
    {
        if (! $txnRef) {
            return null;
        }

        if (! preg_match('/^(\d+)(\d{14})$/', $txnRef, $matches)) {
            return null;
        }

        $booking = Booking::find($matches[1]);
        return $booking?->fixedBooking && $booking->fixedBooking->status !== 'LEGACY' ? null : $booking;
    }

    /**
     * Cancel booking
     */
    public function cancel(Booking $booking, Request $request)
    {
        $this->authorize('cancel', $booking);

        try {
            $this->bookingService->cancelBooking($booking);

            return redirect()
                ->route('bookings.index')
                ->with('success', 'Hủy booking thành công.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * UC38 - Check-out customer (employee only).
     */
    public function checkout(Booking $booking, Request $request)
    {
        abort_unless($request->user()->hasPermission('bookings.checkout'), 403);

        try {
            $booking = $this->bookingService->checkoutBooking($booking, $request->user()->id);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Check-out thành công.',
                    'data' => [
                        'booking_id' => $booking->id,
                        'checked_out_at' => $booking->checked_out_at->toISOString(),
                        'status' => $booking->status,
                    ],
                ]);
            }

            return back()->with('success', 'Check-out thành công. Sân đã sẵn sàng.');
        } catch (\DomainException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }
    }

    // Remove edit, update, destroy methods
    public function edit($id)
    {
        abort(404);
    }

    public function update(Request $request, $id)
    {
        abort(404);
    }

    public function destroy($id)
    {
        abort(404);
    }
}
