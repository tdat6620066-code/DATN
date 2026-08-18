<?php

namespace App\Http\Controllers;

use App\Http\Requests\{StoreBookingRequest, StoreRecurringBookingRequest};
use App\Models\{Booking, Court, TimeSlot};
use App\Services\{BookingService, CourtAvailabilityService, PaymentService, QRCodeService};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookingController extends Controller
{
    private BookingService $bookingService;
    private PaymentService $paymentService;
    private QRCodeService $qrService;

    public function __construct(
        BookingService $bookingService,
        PaymentService $paymentService,
        QRCodeService $qrService
    ) {
        $this->bookingService = $bookingService;
        $this->paymentService = $paymentService;
        $this->qrService = $qrService;
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

        $booking->load('bookingDetails.court', 'bookingDetails.timeSlot', 'payment');

        return view('bookings.show', ['booking' => $booking]);
    }

    /**
     * UC21 - Create recurring booking form
     */
    public function createRecurring(Request $request)
    {
        $courts = Court::where('status', 'ACTIVE')
            ->with('courtType', 'images', 'prices')
            ->get();
        
        $timeSlots = TimeSlot::where('status', 'ACTIVE')->get();

        return view('bookings.create-recurring', [
            'courts' => $courts,
            'timeSlots' => $timeSlots,
        ]);
    }

    /**
     * UC21 - Store recurring booking
     */
    public function storeRecurring(StoreRecurringBookingRequest $request)
    {
        try {
            $booking = $this->bookingService->createRecurringBooking(
                Auth::id(),
                [
                    'court_id' => $request->court_id,
                    'start_date' => $request->start_date,
                    'end_date' => $request->end_date,
                    'days_of_week' => $request->days_of_week,
                    'time_slot_id' => $request->time_slot_id,
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
                'qr_code' => base64_encode($qrCode),
            ])->with('success', 'Thanh toán thành công!');

        } catch (\Exception $e) {
            return back()->with('error', 'Lỗi xử lý thanh toán: ' . $e->getMessage());
        }
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
            $booking = $this->bookingService->checkoutBooking($booking);

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
