<?php

namespace App\Http\Controllers;

use App\Models\{Booking, CounterSale, EmployeeShift, PaymentTransactionLog, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeShiftController extends Controller
{
    public function index(Request $request)
    {
        $shifts = EmployeeShift::where('employee_id', $request->user()->id)->latest()->orderByDesc('id')->paginate(20);
        $selected = $request->filled('shift')
            ? EmployeeShift::where('employee_id', $request->user()->id)->findOrFail($request->query('shift'))
            : $shifts->first();
        return view('employee.shifts', ['shifts' => $shifts, 'selected' => $selected, 'report' => $selected ? $this->report($selected) : null]);
    }

    public function start(Request $request)
    {
        DB::transaction(function () use ($request) {
            User::lockForUpdate()->findOrFail($request->user()->id);
            if (!EmployeeShift::where('employee_id', $request->user()->id)->whereNull('ended_at')->exists()) {
                EmployeeShift::create(['employee_id' => $request->user()->id, 'started_at' => now()]);
            }
        });
        return back()->with('success', 'Ca làm việc đang mở.');
    }

    public function close(Request $request, EmployeeShift $shift)
    {
        abort_unless($shift->employee_id === $request->user()->id, 403);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:3000']]);
        DB::transaction(function () use ($shift, $data) {
            User::lockForUpdate()->findOrFail($shift->employee_id);
            $locked = EmployeeShift::lockForUpdate()->findOrFail($shift->id);
            if (!$locked->ended_at) $locked->update(['ended_at' => now(), 'note' => $data['note'] ?? null]);
        });
        return back()->with('success', 'Đã kết thúc ca.');
    }

    public function export(Request $request, EmployeeShift $shift)
    {
        abort_unless($shift->employee_id === $request->user()->id, 403);
        $report = $this->report($shift);
        return response()->streamDownload(function () use ($shift, $report) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Ca', $shift->id]);
            fputcsv($out, ['Bắt đầu', $shift->started_at->format('d/m/Y H:i:s')]);
            fputcsv($out, ['Kết thúc', $shift->ended_at?->format('d/m/Y H:i:s') ?? 'Đang làm']);
            foreach ($report as $label => $value) fputcsv($out, [$label, $value]);
            fclose($out);
        }, 'ca-'.$shift->id.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function report(EmployeeShift $shift): array
    {
        $end = $shift->ended_at ?? now();
        $logs = PaymentTransactionLog::where('actor_id', $shift->employee_id)->where('action', 'COUNTER_PAYMENT')->where('created_at', '>=', $shift->started_at)->where('created_at', '<', $end)->get();
        return [
            'Số lượt check-in' => Booking::where('checked_in_by', $shift->employee_id)->where('checked_in_at', '>=', $shift->started_at)->where('checked_in_at', '<', $end)->count(),
            'Số lượt check-out' => Booking::where('checked_out_by', $shift->employee_id)->where('checked_out_at', '>=', $shift->started_at)->where('checked_out_at', '<', $end)->count(),
            'Tiền thu booking tại quầy (đ)' => $logs->sum('amount'),
            'Trong đó tiền dịch vụ phát sinh (đ)' => $logs->filter(fn ($log) => ($log->metadata['kind'] ?? '') === 'SERVICE')->sum('amount'),
            'Tiền bán lẻ (đ)' => CounterSale::where('employee_id', $shift->employee_id)->where('created_at', '>=', $shift->started_at)->where('created_at', '<', $end)->sum('total'),
        ];
    }
}
