<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminCustomerController extends Controller
{
    public function index(Request $request)
    {
        $this->admin($request);
        $customers = User::where('role', 'CUSTOMER')->withCount('bookings')->when($request->filled('search'), fn ($q) => $q->where(fn ($i) => $i->where('name', 'like', '%'.$request->search.'%')->orWhere('email', 'like', '%'.$request->search.'%')->orWhere('phone', 'like', '%'.$request->search.'%')))->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))->latest()->paginate(15)->withQueryString();

        return view('admin.customers.index', compact('customers'));
    }

    public function show(User $customer, Request $request)
    {
        $this->customer($customer, $request);
        $customer->loadCount('bookings');
        $bookings = $customer->bookings()->with(['bookingDetails.court', 'payment'])->latest()->paginate(10);

        return view('admin.customers.show', compact('customer', 'bookings'));
    }

    public function edit(User $customer, Request $request)
    {
        $this->customer($customer, $request);

        return view('admin.customers.edit', compact('customer'));
    }

    public function update(User $customer, Request $request)
    {
        $this->customer($customer, $request);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($customer)], 'phone' => ['nullable', 'string', 'max:30']]);
        $customer->update($data);

        return redirect()->route('admin.customers.show', $customer)->with('success', 'Đã cập nhật thông tin khách hàng.');
    }

    public function toggleStatus(User $customer, Request $request)
    {
        $this->customer($customer, $request);
        $customer->update(['status' => $customer->status === 'ACTIVE' ? 'LOCKED' : 'ACTIVE']);

        return back()->with('success', $customer->status === 'LOCKED' ? 'Đã khóa tài khoản khách hàng.' : 'Đã mở khóa tài khoản khách hàng.');
    }

    private function customer(User $customer, Request $request): void
    {
        $this->admin($request);
        abort_unless($customer->role === 'CUSTOMER', 404);
    }

    private function admin(Request $request): void
    {
        abort_unless($request->user()->role === 'ADMIN',403);
    }
}
