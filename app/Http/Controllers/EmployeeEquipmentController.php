<?php

namespace App\Http\Controllers;

use App\Models\{Booking, Equipment, EquipmentLoan, Notification, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EmployeeEquipmentController extends Controller
{
    public function index()
    {
        return view('employee.equipment', [
            'equipment' => Equipment::orderBy('name')->get(),
            'loans' => EquipmentLoan::with('equipment', 'booking.user')->latest()->paginate(20),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:100', 'unique:equipment,code'], 'name' => ['required', 'string', 'max:255'], 'condition_note' => ['required', 'string', 'max:2000']]);
        Equipment::create($data);
        return back()->with('success', 'Đã thêm thiết bị.');
    }

    public function lend(Request $request)
    {
        $data = $request->validate(['equipment_id' => ['required', 'exists:equipment,id'], 'booking_code' => ['required', 'string', 'max:100'], 'issue_note' => ['required', 'string', 'max:2000']]);
        try {
            DB::transaction(function () use ($request, $data) {
                $booking = Booking::where('booking_code', $data['booking_code'])->lockForUpdate()->first();
                if (!$booking || $booking->status !== 'CHECKED_IN') throw new \DomainException('Chỉ cho mượn theo booking đang check-in.');
                $item = Equipment::lockForUpdate()->findOrFail($data['equipment_id']);
                if ($item->status !== 'AVAILABLE') throw new \DomainException('Thiết bị chưa sẵn sàng cho mượn.');
                EquipmentLoan::create(['equipment_id' => $item->id, 'booking_id' => $booking->id, 'issued_by' => $request->user()->id, 'issue_note' => $data['issue_note']]);
                $item->update(['status' => 'ON_LOAN']);
            });
        } catch (\DomainException $e) { return back()->withInput()->with('error', $e->getMessage()); }
        return back()->with('success', 'Đã ghi nhận cho mượn.');
    }

    public function returnLoan(Request $request, EquipmentLoan $loan)
    {
        $data = $request->validate(['return_status' => ['required', Rule::in(['AVAILABLE', 'DAMAGED', 'LOST'])], 'return_note' => ['required', 'string', 'max:2000']]);
        try {
            DB::transaction(function () use ($request, $loan, $data) {
                Booking::lockForUpdate()->findOrFail($loan->booking_id);
                $item = Equipment::lockForUpdate()->findOrFail($loan->equipment_id);
                $locked = EquipmentLoan::lockForUpdate()->findOrFail($loan->id);
                if ($locked->returned_at) throw new \DomainException('Lượt mượn đã được xử lý.');
                $locked->update($data + ['returned_by' => $request->user()->id, 'returned_at' => now()]);
                $item->update(['status' => $data['return_status'], 'condition_note' => $data['return_note']]);
                if ($data['return_status'] !== 'AVAILABLE') {
                    foreach (User::where('role', 'ADMIN')->where('status', 'ACTIVE')->get() as $admin) {
                        Notification::create(['user_id' => $admin->id, 'title' => 'Thiết bị hỏng/mất: '.$item->code, 'content' => $data['return_note'], 'type' => 'SYSTEM', 'is_read' => false]);
                    }
                }
            });
        } catch (\DomainException $e) { return back()->with('error', $e->getMessage()); }
        return back()->with('success', 'Đã ghi nhận trả thiết bị và tình trạng.');
    }

    public function restore(Request $request, Equipment $equipment)
    {
        $data = $request->validate(['condition_note' => ['required', 'string', 'max:2000']]);
        DB::transaction(function () use ($equipment, $data) {
            $item = Equipment::lockForUpdate()->findOrFail($equipment->id);
            abort_if($item->status === 'ON_LOAN', 422, 'Thiết bị đang cho mượn.');
            $item->update($data + ['status' => 'AVAILABLE']);
        });
        return back()->with('success', 'Đã kiểm tra và đưa thiết bị về trạng thái sẵn sàng.');
    }
}
