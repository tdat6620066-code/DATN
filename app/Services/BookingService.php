<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\Court;
use App\Models\CourtPrice;
use App\Models\Holiday;
use App\Models\TimeSlot;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BookingService
{
    private CourtAvailabilityService $availabilityService;

    private VoucherService $voucherService;

    private PaymentService $paymentService;

    private CustomerNotificationService $notifications;

    public function __construct(
        CourtAvailabilityService $availabilityService,
        VoucherService $voucherService,
        PaymentService $paymentService,
        CustomerNotificationService $notifications
    ) {
        $this->availabilityService = $availabilityService;
        $this->voucherService = $voucherService;
        $this->paymentService = $paymentService;
        $this->notifications = $notifications;
    }

    /**
     * Create booking with transaction and locking
     * UC18, UC19, UC20
     */
    public function createBooking($userId, $bookingDetails, $voucherCode = null, array $metadata = [], ?float $allocatedDiscount = null, array $services = [])
    {
        return DB::transaction(function () use ($userId, $bookingDetails, $voucherCode, $metadata, $allocatedDiscount, $services) {
            // Lock all owners before any consistent read, including multi-court requests.
            // This also keeps lock order identical to recurring checkout.
            Court::whereIn('id', array_column($bookingDetails, 'court_id'))->orderBy('id')->lockForUpdate()->get();
            TimeSlot::whereIn('id', array_column($bookingDetails, 'time_slot_id'))->orderBy('id')->lockForUpdate()->get();
            // Validate and lock all booking details
            $maxDays = in_array($metadata['booking_type'] ?? 'daily', ['weekly', 'monthly'], true)
                ? config('booking.max_recurring_days', 365)
                : config('booking.max_days', 30);
            $validatedDetails = $this->validateAndLockBookingDetails($bookingDetails, $maxDays);

            if (! empty($validatedDetails['errors'])) {
                throw new \Exception(json_encode($validatedDetails['errors']));
            }

            // Calculate prices
            $subtotal = $this->calculateSubtotal($validatedDetails['details']);

            // Apply voucher if provided
            $discount = $allocatedDiscount ?? 0;
            if ($discount < 0 || $discount > $subtotal || ($allocatedDiscount !== null && $voucherCode)) {
                throw new \DomainException('Số tiền giảm giá không hợp lệ.');
            }
            $voucherId = null;
            if ($voucherCode) {
                $voucherResult = $this->voucherService->validateAndApply($voucherCode, $subtotal);
                if (! $voucherResult['valid']) {
                    throw new \DomainException($voucherResult['message'] ?? 'Voucher không còn hợp lệ.');
                }
                $discount = $voucherResult['discount'];
                $voucherId = $voucherResult['voucher_id'];
            }

            $totalAmount = max(0, $subtotal - $discount);

            // Create booking
            $booking = Booking::create([
                'booking_code' => $this->generateBookingCode(),
                'user_id' => $userId,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total_amount' => $totalAmount,
                'status' => 'PENDING_PAYMENT',
                'payment_status' => 'PENDING',
                'hold_expires_at' => now()->addMinutes(config('booking.hold_timeout', 5)),
            ] + $metadata);

            // Create booking details
            foreach ($validatedDetails['details'] as $detail) {
                BookingDetail::create([
                    'booking_id' => $booking->id,
                    'court_id' => $detail['court_id'],
                    'booking_date' => $detail['booking_date'],
                    'time_slot_id' => $detail['time_slot_id'],
                    'price' => $detail['price'],
                    'subtotal' => $detail['subtotal'],
                    'status' => 'PENDING',
                ]);
            }

            // Increment voucher usage if applied
            if ($voucherId) {
                $this->voucherService->incrementUsage($voucherId);
            }

            // Create payment record
            if ($services) {
                app(ServiceOrderService::class)->includeInBooking($booking, $services);
                $totalAmount = $booking->total_amount;
            }
            if (empty($metadata['fixed_booking_id'])) {
                $this->paymentService->createPayment($booking, $totalAmount);
            }

            if (empty($metadata['fixed_booking_id'])) {
                $this->notifications->bookingCreated($booking);
            }

            return $booking;
        }, 3); // 3 retry attempts
    }

    /**
     * Validate and lock all booking details
     * Uses SELECT FOR UPDATE to prevent race conditions
     */
    private function validateAndLockBookingDetails($bookingDetails, ?int $maxDays = null)
    {
        $errors = [];
        $validatedDetails = [];

        foreach ($bookingDetails as $detail) {
            $court = Court::lockForUpdate()->find($detail['court_id']);
            $timeSlot = TimeSlot::lockForUpdate()->find($detail['time_slot_id']);
            $bookingDate = Carbon::parse($detail['booking_date']);

            // Validate court
            if (! $court) {
                $errors[] = [
                    'booking_date' => $detail['booking_date'],
                    'time_slot_id' => $detail['time_slot_id'],
                    'message' => 'Sân không tồn tại',
                ];

                continue;
            }

            if ($court->status !== 'ACTIVE') {
                $errors[] = [
                    'booking_date' => $detail['booking_date'],
                    'time_slot_id' => $detail['time_slot_id'],
                    'message' => 'Sân không hoạt động',
                ];

                continue;
            }

            // Validate time slot
            if (! $timeSlot || $timeSlot->status !== 'ACTIVE') {
                $errors[] = [
                    'booking_date' => $detail['booking_date'],
                    'time_slot_id' => $detail['time_slot_id'],
                    'message' => 'Khung giờ không hợp lệ',
                ];

                continue;
            }

            // Validate booking date
            if (! $this->isValidBookingDate($bookingDate, $timeSlot, $maxDays)) {
                $errors[] = [
                    'booking_date' => $detail['booking_date'],
                    'time_slot_id' => $detail['time_slot_id'],
                    'message' => 'Ngày đặt không hợp lệ',
                ];

                continue;
            }

            // Check availability
            $availability = $this->availabilityService->checkAvailability(
                $court->id,
                $bookingDate,
                $timeSlot->id
            );

            if ($availability !== CourtAvailabilityService::STATUS_AVAILABLE) {
                $errors[] = [
                    'booking_date' => $detail['booking_date'],
                    'time_slot_id' => $detail['time_slot_id'],
                    'message' => $this->getAvailabilityErrorMessage($availability),
                ];

                continue;
            }

            // Get current price
            $price = $this->getCurrentPrice($court->id, $timeSlot->id, $bookingDate);
            if (! $price) {
                $errors[] = [
                    'booking_date' => $detail['booking_date'],
                    'time_slot_id' => $detail['time_slot_id'],
                    'message' => 'Không có giá cho khung giờ này',
                ];

                continue;
            }

            // Validate no duplicates in request
            $exists = collect($validatedDetails)->contains(function ($v) use ($detail) {
                return $v['court_id'] == $detail['court_id']
                    && $v['booking_date'] == $detail['booking_date']
                    && $v['time_slot_id'] == $detail['time_slot_id'];
            });

            if ($exists) {
                $errors[] = [
                    'booking_date' => $detail['booking_date'],
                    'time_slot_id' => $detail['time_slot_id'],
                    'message' => 'Khung giờ này bị trùng trong yêu cầu',
                ];

                continue;
            }

            // All validations passed
            $validatedDetails[] = [
                'court_id' => $court->id,
                'booking_date' => $bookingDate,
                'time_slot_id' => $timeSlot->id,
                'price' => $price,
                'subtotal' => $price,
            ];
        }

        foreach (collect($validatedDetails)->groupBy(fn ($detail) => $detail['court_id'].'-'.$detail['booking_date']->toDateString()) as $group) {
            if (! TimeSlot::areConsecutive(TimeSlot::whereIn('id', $group->pluck('time_slot_id'))->get())) {
                $errors[] = [
                    'booking_date' => $group->first()['booking_date']->toDateString(),
                    'time_slot_id' => $group->first()['time_slot_id'],
                    'message' => 'Chỉ được chọn các khung giờ liền nhau, không cách quãng hoặc chồng lấn.',
                ];
            }
        }

        return [
            'errors' => $errors,
            'details' => $validatedDetails,
        ];
    }

    /**
     * Validate booking date
     */
    private function isValidBookingDate(Carbon $date, TimeSlot $timeSlot, ?int $maxDays = null)
    {
        $maxDays ??= config('booking.max_days', 30);

        // A booking is valid for the whole current day. Comparing a date at
        // midnight with the current time previously rejected every booking made today.
        if ($date->copy()->startOfDay()->lt(now()->startOfDay())) {
            return false;
        }

        // Must be within allowed days
        if ($date > now()->addDays($maxDays)) {
            return false;
        }

        // A slot may not be booked once its start time has passed.
        $slotStart = Carbon::parse($date->toDateString().' '.$timeSlot->start_time);
        if ($slotStart->lte(now())) {
            return false;
        }

        return true;
    }

    /**
     * Get current price for court and time slot on booking date
     */
    public function getCurrentPrice($courtId, $timeSlotId, Carbon $date)
    {
        // Some existing databases predate advanced pricing and do not have a
        // holidays table yet. They should continue to use weekday/weekend
        // prices instead of preventing every booking.
        $isHoliday = Schema::hasTable('holidays')
            && Holiday::whereDate('holiday_date', $date)->exists();

        $dayType = $isHoliday
            ? 'HOLIDAY'
            : ($date->isWeekend() ? 'WEEKEND' : 'WEEKDAY');
        $hasDayType = Schema::hasColumn('court_prices', 'day_type');

        $findPrice = function (?string $type = null) use ($courtId, $timeSlotId, $date, $hasDayType) {
            return CourtPrice::where('court_id', $courtId)
                ->where('time_slot_id', $timeSlotId)
                ->when($hasDayType && $type, fn ($query) => $query->where('day_type', $type))
                ->where('status', 'ACTIVE')
                ->where('effective_from', '<=', $date->toDateString())
                ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', $date->toDateString()))
                ->latest('effective_from')
                ->first();
        };

        $price = $findPrice($dayType);

        if (! $price && $hasDayType && $dayType !== 'WEEKDAY') {
            $price = $findPrice('WEEKDAY');
        }

        return $price ? $price->price : null;
    }

    /**
     * Calculate subtotal from details
     */
    private function calculateSubtotal($details)
    {
        return collect($details)->sum('subtotal');
    }

    /**
     * Generate unique booking code
     */
    private function generateBookingCode()
    {
        do {
            $code = 'BK'.date('Ymd').strtoupper(Str::random(6));
        } while (Booking::where('booking_code', $code)->exists());

        return $code;
    }

    /**
     * Get availability error message
     */
    private function getAvailabilityErrorMessage($status)
    {
        return match ($status) {
            CourtAvailabilityService::STATUS_BOOKED => 'Khung giờ này đã được đặt',
            CourtAvailabilityService::STATUS_HOLD => 'Khung giờ này đang được giữ',
            CourtAvailabilityService::STATUS_MAINTENANCE => 'Sân đang bảo trì trong khung giờ này',
            default => 'Khung giờ không khả dụng'
        };
    }

    /**
     * Build a recurring schedule and report every unavailable occurrence.
     * This method is deliberately read-only: it never creates a booking or
     * reserves a slot until the customer explicitly confirms the preview.
     */
    public function previewRecurringBooking(array $recurringData): array
    {
        $details = $this->generateRecurringBookingDetails($recurringData);
        $court = Court::find($recurringData['court_id']);
        $schedules = [];
        $conflicts = [];
        $subtotal = 0;

        foreach ($details as $detail) {
            $date = Carbon::parse($detail['booking_date']);
            $timeSlot = TimeSlot::find($detail['time_slot_id']);
            $reason = null;

            if (! $court || $court->status !== 'ACTIVE') {
                $reason = 'Sân hiện không hoạt động';
            } elseif (! $timeSlot || $timeSlot->status !== 'ACTIVE') {
                $reason = 'Khung giờ không hợp lệ';
            } elseif (! $this->isValidBookingDate($date, $timeSlot, config('booking.max_recurring_days', 365))) {
                $reason = 'Ngày hoặc giờ đặt không còn hợp lệ';
            } else {
                $availability = $this->availabilityService->checkAvailability($court->id, $date, $timeSlot->id);
                if ($availability !== CourtAvailabilityService::STATUS_AVAILABLE) {
                    $reason = $this->getAvailabilityErrorMessage($availability);
                }
            }

            $price = $reason ? 0 : $this->getCurrentPrice($court->id, $timeSlot->id, $date);
            if (! $reason && ! $price) {
                $reason = 'Chưa có giá cho khung giờ này';
            }

            $item = [
                'key' => $detail['booking_date'].'-'.$detail['time_slot_id'],
                'court_id' => (int) $detail['court_id'],
                'court_name' => $court?->name ?? '—',
                'time_slot_id' => (int) $detail['time_slot_id'],
                'booking_date' => $detail['booking_date'],
                'start_time' => $timeSlot?->start_time,
                'end_time' => $timeSlot?->end_time,
                'date' => $date,
                'time_slot' => $timeSlot?->name ?? '—',
                'price' => $price,
                'reason' => $reason,
            ];

            if ($reason) {
                $conflicts[] = $item;
            } else {
                $schedules[] = $item;
                $subtotal += $price;
            }
        }

        return compact('schedules', 'conflicts', 'subtotal');
    }

    /**
     * Generate recurring booking details
     */
    private function generateRecurringBookingDetails($recurringData)
    {
        $startDate = Carbon::parse($recurringData['start_date']);
        $endDate = Carbon::parse($recurringData['end_date']);
        $bookingType = $recurringData['booking_type'] ?? 'weekly';
        $daysOfWeek = $recurringData['days_of_week'] ?? [];
        $daysOfMonth = $recurringData['days_of_month'] ?? [];
        $timeSlotIds = $recurringData['time_slot_ids'] ?? [$recurringData['time_slot_id']];
        $courtId = $recurringData['court_id'];

        $bookingDetails = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $matchesRule = $bookingType === 'monthly'
                ? in_array($current->day, $daysOfMonth)
                : in_array($current->dayOfWeek, $daysOfWeek);

            if ($matchesRule) {
                foreach ($timeSlotIds as $timeSlotId) {
                    $bookingDetails[] = [
                        'court_id' => $courtId,
                        'booking_date' => $current->toDateString(),
                        'time_slot_id' => $timeSlotId,
                    ];
                }
            }
            $current->addDay();
        }

        return $bookingDetails;
    }

    /**
     * Cancel booking
     */
    public function cancelBooking(Booking $booking)
    {
        if ($booking->fixedBooking && $booking->fixedBooking->status !== 'LEGACY') {
            throw new \DomainException('Lịch cố định thanh toán một lần. Vui lòng đặt lại lịch nếu cần thay đổi danh sách buổi.');
        }
        return DB::transaction(function () use ($booking) {
            $booking->loadMissing('payment');

            if (in_array($booking->payment_status, ['PAID', 'REFUNDED', 'PARTIALLY_REFUNDED'], true) || in_array($booking->payment?->status, ['PAID', 'REFUNDED', 'PARTIALLY_REFUNDED'], true)) {
                throw new \DomainException('Đơn đã thanh toán không thể hủy.');
            }

            if ($booking->status !== 'PENDING_PAYMENT') {
                throw new \Exception('Không thể hủy booking ở trạng thái '.$booking->status);
            }

            $booking->update([
                'status' => 'CANCELLED',
                'cancelled_at' => now(),
            ]);

            // Update booking details
            foreach ($booking->bookingDetails as $detail) {
                $detail->update(['status' => 'CANCELLED']);
            }

            return $booking;
        });
    }

    /**
     * UC38 - Check out a customer who is currently using the court.
     */
    public function checkoutBooking(Booking $booking, ?int $employeeId = null): Booking
    {
        return app(BookingOperationsService::class)->checkout($booking, \App\Models\User::findOrFail($employeeId));
    }

    /**
     * Get booking details for user
     */
    public function getBookingDetails(Booking $booking, $userId)
    {
        // Authorize: user can only see their own bookings
        if ($booking->user_id !== $userId) {
            throw new \Exception('Không có quyền truy cập booking này');
        }

        return $booking;
    }
}
