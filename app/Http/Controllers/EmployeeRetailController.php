<?php

namespace App\Http\Controllers;

use App\Models\{CounterSale, ServiceItem, SystemSetting, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EmployeeRetailController extends Controller
{
    public function index(Request $request)
    {
        $activeBookings = $request->user()->hasPermission('services.manage')
            ? \App\Models\Booking::where('status', 'CHECKED_IN')->with(['user', 'bookingDetails.court', 'bookingDetails.timeSlot'])->latest()->get()
            : collect();
        $selectedBooking = $activeBookings->firstWhere('id', $request->query('booking_id'));
        return view('employee.retail.index', [
            'activeBookings' => $activeBookings,
            'selectedBooking' => $selectedBooking,
            'items' => ServiceItem::where('is_active', true)->orderBy('name')->get(),
            'sales' => CounterSale::where('employee_id', $request->user()->id)->latest()->orderByDesc('id')->paginate(15),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'request_key' => ['required', 'uuid'], 'customer_name' => ['nullable', 'string', 'max:255'],
            'payment_method' => ['required', Rule::in(['CASH', 'BANK_TRANSFER', 'QR'])],
            'transaction_id' => ['nullable', 'required_unless:payment_method,CASH', 'string', 'max:100'],
            'quantities' => ['required', 'array', 'max:200'],
            'quantities.*' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ]);
        $quantities = collect($data['quantities'])->filter(fn ($quantity) => $quantity > 0)->sortKeys();
        if ($quantities->isEmpty()) return back()->withInput()->with('error', 'Chọn ít nhất một sản phẩm hoặc dịch vụ.');
        try {
            $sale = DB::transaction(function () use ($request, $data, $quantities) {
                // Serialize double submissions from the same cashier before checking the request key.
                User::lockForUpdate()->findOrFail($request->user()->id);
                $existing = CounterSale::where('request_key', $data['request_key'])->first();
                if ($existing) { abort_unless($existing->employee_id === $request->user()->id, 403); return $existing; }
                if ($data['payment_method'] === 'CASH' && ! (bool) SystemSetting::valueFor('cash_enabled', '1')) throw new \DomainException('Thanh toán tiền mặt đang tạm ngừng.');
                $lines = [];
                foreach ($quantities as $id => $quantity) {
                    $item = ServiceItem::where('is_active', true)->lockForUpdate()->findOrFail($id);
                    if ($item->stock !== null && $item->stock < $quantity) throw new \DomainException('Không đủ tồn kho: '.$item->name);
                    if ($item->stock !== null) $item->decrement('stock', $quantity);
                    $lines[] = ['service_item_id' => $item->id, 'name' => $item->name, 'quantity' => $quantity, 'unit_price' => $item->price, 'subtotal' => round((float) $item->price * $quantity, 2)];
                }
                $sale = CounterSale::create([
                    'request_key' => $data['request_key'], 'employee_id' => $request->user()->id,
                    'customer_name' => $data['customer_name'] ?? null, 'total' => array_sum(array_column($lines, 'subtotal')),
                    'payment_method' => $data['payment_method'], 'transaction_id' => $data['transaction_id'] ?? null,
                ]);
                $sale->lines()->createMany($lines);
                return $sale;
            });
        } catch (\DomainException $e) { return back()->withInput()->with('error', $e->getMessage()); }
        return redirect()->route('employee.retail.show', $sale)->with('success', 'Đã ghi nhận thanh toán và xuất hóa đơn bán lẻ.');
    }

    public function show(Request $request, CounterSale $sale)
    {
        abort_unless($sale->employee_id === $request->user()->id, 403);
        return view('employee.retail.show', ['sale' => $sale->load('lines', 'employee')]);
    }
}
