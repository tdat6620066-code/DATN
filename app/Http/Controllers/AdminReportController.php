<?php

namespace App\Http\Controllers;

use App\Services\OperationsReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AdminReportController extends Controller
{
    public function index(Request $request, OperationsReportService $reports)
    {
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $from = Carbon::parse($data['from'] ?? now()->startOfMonth()->toDateString())->startOfDay();
        $to = Carbon::parse($data['to'] ?? now()->toDateString())->endOfDay();
        abort_if($from > $to || $from->diffInDays($to) > 366, 422);

        return view('admin.reports.index', ['from' => $from, 'to' => $to, 'bookings' => $reports->bookings($from, $to), 'refunds' => $reports->refunds($from, $to)]);
    }
}
