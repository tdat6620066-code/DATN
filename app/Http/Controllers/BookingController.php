<?php

namespace App\Http\Controllers;

use App\Http\Requests\{StoreBookingRequest, StoreRecurringBookingRequest};
use App\Models\{Booking, Court, TimeSlot};
use App\Services\{BookingService, CourtAvailabilityService, PaymentService, QRCodeService, VnPayService};
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
        $bookings = Booking::where('user_id', Auth::id())
            ->with('bookingDetails.court', 'bookingDetails.timeSlot', 'payment')
            ->orderByDesc('created_at')
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
                            ->where(function($q) use ($bookingDate) {
                                $q->whereNull('effective_to')
                                  ->orWhere('effective_to', '>=', $bookingDate->toDateString());
                            })
                            ->first()?->price ?? 0
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
                Auth::id(),
                $bookingDetails,
                $request->voucher_code
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

        // Luồng ngoại lệ: booking không hợp lệ → không tạo QR
        if (! in_array($booking->status, ['CONFIRMED', 'CHECKED_IN'], true)) {
            return redirect()
                ->route('bookings.show', $booking)
                ->with('error', 'Mã QR chỉ khả dụng cho booking đã xác nhận và chưa hoàn thành/hủy.');
        }

        $qrCode = $this->qrService->generateQRCode($booking);

        return view('bookings.qr', [
            'booking' => $booking,
            'qr_code' => $qrCode,
        ]);
    }

    /**
     * UC21 - Create recurring booking form
     */
    public function createRecurring(Request $request)
    {
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
            'bookingType' => $request->query('booking_type', 'weekly'),
        ]);
    }

    /**
     * UC21 - Generate a recurring schedule without creating a booking.
     */
    public function previewRecurring(StoreRecurringBookingRequest $request)
    {
        $data = $request->validated();
        $preview = $this->bookingService->previewRecurringBooking($data);

        $courts = Court::where('status', 'ACTIVE')->with('courtType')->get();
        $timeSlots = TimeSlot::where('status', 'ACTIVE')->get();
        $selectedCourt = Court::where('status', 'ACTIVE')->with('courtType')->find($data['court_id']);

        if ($request->boolean('from_court')) {
            return redirect()->route('courts.show', $data['court_id'])
                ->with('recurring_preview', $preview)
                ->withInput();
        }

        return view('bookings.create-recurring', compact('courts', 'selectedCourt', 'timeSlots', 'preview'));
    }

    /**
     * UC21 - Store recurring booking
     */
    public function storeRecurring(StoreRecurringBookingRequest $request)
    {
        $request->validate([
            'confirmed' => ['accepted'],
        ], [
            'confirmed.accepted' => 'Vui lòng kiểm tra lịch dự kiến và xác nhận trước khi tạo booking.',
        ]);

        try {
            $booking = $this->bookingService->createRecurringBooking(
                Auth::id(),
                [
                    'court_id' => $request->court_id,
                    'start_date' => $request->start_date,
                    'end_date' => $request->end_date,
                    'days_of_week' => $request->days_of_week,
                    'days_of_month' => $request->days_of_month,
                    'time_slot_ids' => $request->time_slot_ids,
                    'time_slot_id' => $request->time_slot_id,
                    'booking_type' => $request->booking_type ?? 'weekly',
                ],
                $request->voucher_code
            );

            return redirect()
                ->route('bookings.show', $booking)
                ->with('success', 'Đặt sân định kỳ thành công. Vui lòng hoàn tất thanh toán.');

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
     * Process payment confirmation
     */
    public function confirmPayment(Booking $booking, Request $request)
    {
        $this->authorize('confirmPayment', $booking);

        if ($booking->status !== 'PENDING_PAYMENT') {
            return back()->with('error', 'Booking này không thể thanh toán');
        }

        try {
            // Mark payment as paid
            $payment = $this->paymentService->markAsPaid(
                $booking->payment,
                $request->transaction_id
            );

            // Generate QR code
            $qrCode = $this->qrService->generateQRCode($booking);

            return view('bookings.success', [
                'booking' => $booking->refresh(),
                'qr_code' => $qrCode,
            ])->with('success', 'Thanh toán thành công!');

        } catch (\Exception $e) {
            return back()->with('error', 'Lỗi xử lý thanh toán: ' . $e->getMessage());
        }
    }

    /**
     * Tạo URL thanh toán VNPay và chuyển hướng user sang trang thanh toán.
     */
    public function vnpayCreate(Booking $booking)
    {
        $this->authorize('confirmPayment', $booking);

        if ($booking->status !== 'PENDING_PAYMENT') {
            return back()->with('error', 'Booking này không thể thanh toán.');
        }

        if ($this->expireHoldIfNeeded($booking)) {
            return redirect()->route('bookings.show', $booking)
                ->with('error', 'Thời gian giữ chỗ đã hết. Vui lòng chọn lại khung giờ.');
        }

        $returnUrl = route('bookings.vnpay.return');
        $paymentUrl = $this->vnpayService->createPaymentUrl($booking, $returnUrl);

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
        if ($booking->status !== 'PENDING_PAYMENT' || ! $booking->isHoldExpired()) {
            return false;
        }

        $booking->loadMissing('payment');
        $booking->update(['status' => 'EXPIRED']);
        $booking->bookingDetails()->update(['status' => 'CANCELLED']);
        $booking->payment?->update(['status' => 'FAILED']);

        return true;
    }

    /**
     * VNPay redirect user về đây sau khi thanh toán.
     * Trạng thái thật được IPN cập nhật; tại đây chỉ hiển thị kết quả cho user.
     */
    public function vnpayReturn(Request $request)
    {
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

        if (($data['vnp_ResponseCode'] ?? null) === '00') {
            // Đảm bảo trạng thái PAID nếu IPN chưa kịp chạy (idempotent).
            if ($booking->payment && $booking->payment->status !== 'PAID') {
                $this->paymentService->markAsPaid(
                    $booking->payment,
                    $data['vnp_TransactionNo'] ?? $data['vnp_TxnRef'],
                    'vnpay'
                );
            }

            return redirect()->route('bookings.show', $booking)
                ->with('success', 'Thanh toán VNPay thành công.');
        }

        return redirect()->route('bookings.show', $booking)
            ->with('error', 'Thanh toán VNPay thất bại hoặc đã bị hủy.');
    }

    /**
     * IPN URL: VNPay gọi trực tiếp để xác nhận kết quả giao dịch.
     */
    public function vnpayIpn(Request $request)
    {
        $data = $request->all();

        if (! $this->vnpayService->verifyResponse($data)) {
            return response()->json(['RspCode' => '97', 'Message' => 'Invalid Checksum']);
        }

        $booking = $this->resolveBookingFromTxnRef($data['vnp_TxnRef'] ?? null);

        if (! $booking) {
            return response()->json(['RspCode' => '01', 'Message' => 'Order not found']);
        }

        if (($data['vnp_ResponseCode'] ?? null) !== '00') {
            return response()->json(['RspCode' => '00', 'Message' => 'Confirm Success']);
        }

        // Xác nhận số tiền khớp với booking.
        $expectedAmount = (int) round((float) $booking->total_amount * 100);
        $receivedAmount = (int) ($data['vnp_Amount'] ?? 0);

        if ($receivedAmount !== $expectedAmount) {
            return response()->json(['RspCode' => '04', 'Message' => 'Invalid amount']);
        }

        if ($booking->payment && $booking->payment->status !== 'PAID') {
            $this->paymentService->markAsPaid(
                $booking->payment,
                $data['vnp_TransactionNo'] ?? $data['vnp_TxnRef'],
                'vnpay'
            );
        }

        return response()->json(['RspCode' => '00', 'Message' => 'Confirm Success']);
    }

    /**
     * Phân giải booking từ vnp_TxnRef ({booking_code}_{YmdHis}).
     */
    private function resolveBookingFromTxnRef(?string $txnRef): ?Booking
    {
        if (! $txnRef) {
            return null;
        }

        $bookingCode = explode('_', $txnRef)[0] ?? $txnRef;

        return Booking::where('booking_code', $bookingCode)->first();
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
    public function edit($id) { abort(404); }
    public function update(Request $request, $id) { abort(404); }
    public function destroy($id) { abort(404); }
}
