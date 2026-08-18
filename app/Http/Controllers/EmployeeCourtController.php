<?php

namespace App\Http\Controllers;

use App\Models\Court;
use App\Services\CourtStatusService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeCourtController extends Controller
{
    public function __construct(private readonly CourtStatusService $service) {}

    public function index(Request $request)
    {
        if ($request->user()->role === 'ADMIN') {
            return redirect()->route('admin.courts.index');
        }

        $this->authorizeEmployee($request);
        $courts = Court::with('courtType')->orderBy('name')->paginate(20);

        return view('employee.courts.index', compact('courts'));
    }

    public function edit(Court $court, Request $request)
    {
        $this->authorizeEmployee($request);
        $court->load(['courtType', 'bookingDetails' => fn ($query) => $query
            ->whereDate('booking_date', '>=', today())
            ->whereHas('booking', fn ($booking) => $booking->whereIn('status', ['CONFIRMED', 'CHECKED_IN']))
            ->with(['booking.user', 'timeSlot'])]);

        return view('employee.courts.edit', compact('court'));
    }

    public function update(Court $court, Request $request)
    {
        $this->authorizeEmployee($request);
        $data = $request->validate([
            'operational_status' => ['required', Rule::in(['AVAILABLE', 'LOCKED', 'MAINTENANCE'])],
            'status_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $court = $this->service->update($court, $data['operational_status'], $data['status_reason'] ?? null);
        } catch (\DomainException $e) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], 422)
                : back()->withInput()->with('error', $e->getMessage());
        }

        return $request->expectsJson()
            ? response()->json(['message' => 'Đã cập nhật trạng thái sân.', 'data' => $court])
            : redirect()->route('employee.courts.index')->with('success', 'Đã cập nhật trạng thái sân.');
    }

    private function authorizeEmployee(Request $request): void
    {
        abort_unless($request->user()->hasPermission('courts.status.manage'), 403);
    }
}
