<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AdminEmployeeController extends Controller
{
    private const DASHBOARD_PERMISSION = ['employee.dashboard' => 'Truy cập tổng quan nhân viên'];

    public const PERMISSIONS = ['bookings.view' => 'Xem lịch và đơn đặt sân', 'bookings.checkin' => 'Check-in khách hàng', 'bookings.checkout' => 'Hoàn thành lượt chơi', 'payments.counter' => 'Thanh toán tại quầy', 'services.manage' => 'Quản lý dịch vụ phát sinh', 'incidents.manage' => 'Báo cáo sự cố sân', 'refunds.manage' => 'Xử lý hủy / hoàn tiền', 'courts.status.manage' => 'Quản lý trạng thái sân'];

    public function index(Request $request)
    {
        $this->admin($request);
        $employees = User::whereIn('role', ['EMPLOYEE', 'ADMIN'])->when($request->filled('search'), fn ($q) => $q->where(fn ($i) => $i->where('name', 'like', '%'.$request->search.'%')->orWhere('email', 'like', '%'.$request->search.'%')))->latest()->paginate(15)->withQueryString();

        return view('admin.employees.index', ['employees' => $employees, 'permissionOptions' => self::DASHBOARD_PERMISSION + self::PERMISSIONS]);
    }

    public function create(Request $request)
    {
        $this->admin($request);

        return view('admin.employees.form', ['employee' => null, 'permissionOptions' => self::DASHBOARD_PERMISSION + self::PERMISSIONS]);
    }

    public function store(Request $request)
    {
        $this->admin($request);
        $data = $this->validated($request);
        $data['password'] = Hash::make($data['password']);
        $data['status'] = 'ACTIVE';
        $data['permissions'] = $data['role'] === 'ADMIN' ? [] : ($data['permissions'] ?? []);
        User::create($data);

        return redirect()->route('admin.employees.index')->with('success', 'Đã tạo tài khoản nhân viên.');
    }

    public function edit(User $employee, Request $request)
    {
        $this->staff($employee, $request);

        return view('admin.employees.form', compact('employee') + ['permissionOptions' => self::DASHBOARD_PERMISSION + self::PERMISSIONS]);
    }

    public function update(User $employee, Request $request)
    {
        $this->staff($employee, $request);
        $data = $this->validated($request, $employee);
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }$data['permissions'] = $data['role'] === 'ADMIN' ? [] : ($data['permissions'] ?? []);
        $employee->update($data);

        return redirect()->route('admin.employees.index')->with('success', 'Đã cập nhật tài khoản và quyền.');
    }

    public function toggleStatus(User $employee, Request $request)
    {
        $this->staff($employee, $request);
        abort_if($employee->is($request->user()), 422, 'Không thể tự khóa tài khoản đang đăng nhập.');
        $employee->update(['status' => $employee->status === 'ACTIVE' ? 'LOCKED' : 'ACTIVE']);

        return back()->with('success', 'Đã cập nhật trạng thái tài khoản.');
    }

    private function validated(Request $request, ?User $employee = null): array
    {
        return $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($employee)], 'phone' => ['nullable', 'string', 'max:30'], 'password' => [$employee ? 'nullable' : 'required', 'confirmed', Password::min(8)], 'role' => ['required', Rule::in(['EMPLOYEE', 'ADMIN'])], 'refund_approval_limit' => ['nullable', 'numeric', 'min:0'], 'permissions' => ['nullable', 'array'], 'permissions.*' => [Rule::in(array_keys(self::DASHBOARD_PERMISSION + self::PERMISSIONS))]]);
    }

    private function staff(User $employee, Request $request): void
    {
        $this->admin($request);
        abort_unless(in_array($employee->role, ['EMPLOYEE', 'ADMIN'], true), 404);
    }

    private function admin(Request $request): void
    {
        abort_unless($request->user()->role === 'ADMIN',403);
    }
}
