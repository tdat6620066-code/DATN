<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\RefundRequest;
use App\Services\RefundRequestService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RefundRequestController extends Controller
{
    public function __construct(private readonly RefundRequestService $service) {}

    public function index(Request $request)
    {
        $this->authorizeEmployee($request);
        $requests = RefundRequest::with(['booking', 'requester', 'refund'])
            ->latest()->paginate(20);

        return view('employee.refund-requests.index', compact('requests'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'booking_id' => ['required', 'integer', 'exists:bookings,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'supporting_information' => ['nullable', 'string', 'max:5000'],
        ]);

        $booking = Booking::with('payment')->findOrFail($data['booking_id']);
        abort_unless($booking->user_id === $request->user()->id, 403);
        if (! $booking->payment || $booking->payment->status !== 'PAID') {
            return response()->json(['message' => 'Booking chưa thanh toán nên không thể yêu cầu hoàn tiền.'], 422);
        }
        if ((float) $data['amount'] > (float) $booking->payment->amount) {
            return response()->json(['message' => 'Số tiền yêu cầu vượt quá số tiền đã thanh toán.'], 422);
        }

        $refundRequest = RefundRequest::create($data + [
            'requested_by' => $request->user()->id,
            'status' => 'PENDING',
        ]);

        return response()->json(['message' => 'Đã gửi yêu cầu hoàn tiền.', 'data' => $refundRequest], 201);
    }

    public function show(RefundRequest $refundRequest, Request $request)
    {
        $this->authorizeEmployee($request);
        $refundRequest->load(['booking.payment', 'booking.bookingDetails.court', 'requester', 'reviewer', 'refund']);

        return view('employee.refund-requests.show', compact('refundRequest'));
    }

    public function review(RefundRequest $refundRequest, Request $request)
    {
        $this->authorizeEmployee($request);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['APPROVED', 'REJECTED', 'NEEDS_INFO'])],
            'decision_note' => ['nullable', 'string', 'max:2000'],
            'requested_information' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $result = $this->service->review($refundRequest, $request->user(), $data);
        } catch (\DomainException $e) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], 422)
                : back()->withInput()->with('error', $e->getMessage());
        }

        return $request->expectsJson()
            ? response()->json(['message' => 'Đã xử lý yêu cầu.', 'data' => $result])
            : redirect()->route('employee.refund-requests.show', $result)->with('success', 'Đã xử lý yêu cầu hoàn tiền.');
    }

    private function authorizeEmployee(Request $request): void
    {
        abort_unless($request->user()->hasPermission('refunds.manage'), 403);
    }
}
