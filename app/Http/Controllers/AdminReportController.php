<?php

namespace App\Http\Controllers;

use App\Services\OperationsReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AdminReportController extends Controller
{
    public function export(Request $request, \App\Services\RevenueReportService $reports)
    {
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $from = Carbon::parse($data['from'] ?? now()->startOfMonth()->toDateString())->startOfDay();
        $to = Carbon::parse($data['to'] ?? now()->toDateString())->endOfDay();
        abort_if($from > $to || $from->diffInDays($to) > 366, 422);
        $cash = $reports->cashFlow($from, $to);
        $revenue = $reports->courtRevenue($from, $to);
        $rows = [
            ['Chỉ tiêu', 'Giá trị'], ['Từ ngày', $from->toDateString()], ['Đến ngày', $to->toDateString()],
            ['Tiền đã thu', $cash['gross_revenue']], ['Tiền đã hoàn', $cash['refund_amount']],
            ['Dòng tiền ròng', $cash['net_revenue']], ['Doanh thu sân theo ngày thanh toán', $revenue['revenue']],
            ['Khách hàng mới trong kỳ', \App\Models\User::where('role', 'CUSTOMER')->whereBetween('created_at', [$from, $to])->count()],
        ];
        foreach (\App\Models\Court::selectRaw('status, COUNT(*) as total')->groupBy('status')->get() as $court) {
            $rows[] = ['Sân hiện tại: '.$court->status, $court->total];
        }
        foreach ($revenue['courts'] as $court) $rows[] = ['Doanh thu sân: '.$court['name'], $court['amount']];
        $labels = ['CASH' => 'Tiền mặt', 'VNPAY' => 'VNPay', 'BANK_TRANSFER' => 'Chuyển khoản ngân hàng', 'MOMO' => 'MoMo', 'UNKNOWN' => 'Chưa xác định'];
        $rows[] = [];
        $rows[] = ['Loại giao dịch', 'Ngày / sân', 'Phương thức', 'Số tiền'];
        foreach (['receipt_methods_daily' => 'Thu tiền', 'refund_methods_daily' => 'Hoàn tiền'] as $key => $type) {
            foreach ($cash[$key]->sortKeysDesc() as $date => $methods) {
                foreach ($methods as $method => $amount) $rows[] = [$type, $date, $labels[strtoupper($method)] ?? $method, $amount];
            }
        }
        foreach ($revenue['courts'] as $court) {
            foreach ($court['methods'] as $method => $amount) $rows[] = ['Thu từ sân', $court['name'], $labels[strtoupper($method)] ?? $method, $amount];
        }
        return response()->streamDownload(function () use ($rows) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                $row = array_map(fn ($value) => is_string($value) && preg_match('/^[=+@\-\t\r]/', $value) ? "'".$value : $value, $row);
                fputcsv($output, $row);
            }
            fclose($output);
        }, 'smashzone-report-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function index(Request $request, OperationsReportService $reports, \App\Services\RevenueReportService $revenue)
    {
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $from = Carbon::parse($data['from'] ?? now()->startOfMonth()->toDateString())->startOfDay();
        $to = Carbon::parse($data['to'] ?? now()->toDateString())->endOfDay();
        abort_if($from > $to || $from->diffInDays($to) > 366, 422);

        return view('admin.reports.index', ['from' => $from, 'to' => $to, 'revenue' => $revenue->courtRevenue($from, $to), 'bookings' => $reports->bookings($from, $to), 'refunds' => $reports->refunds($from, $to)]);
    }

    public function cashFlow(Request $request, \App\Services\RevenueReportService $reports)
    {
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $from = Carbon::parse($data['from'] ?? now()->startOfMonth()->toDateString())->startOfDay();
        $to = Carbon::parse($data['to'] ?? now()->toDateString())->endOfDay();
        abort_if($from > $to || $from->diffInDays($to) > 366, 422);
        return view('admin.reports.cash-flow', ['from' => $from, 'to' => $to, 'cash' => $reports->cashFlow($from, $to)]);
    }
}
