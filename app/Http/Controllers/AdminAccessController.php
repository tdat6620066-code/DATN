<?php
namespace App\Http\Controllers;

use App\Models\{AccessRole, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminAccessController extends Controller
{
    public static function permissions(): array
    {
        return ['employee.dashboard' => 'Xem tổng quan nhân viên'] + AdminEmployeeController::PERMISSIONS;
    }

    public function roles()
    {
        return view('admin.access.roles', ['roles' => AccessRole::orderBy('name')->get(), 'permissions' => self::permissions()]);
    }

    public function saveRole(Request $request, ?AccessRole $accessRole = null)
    {
        $role = $accessRole ?? new AccessRole;
        $data = $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('access_roles', 'name')->ignore($role->id)], 'permissions' => ['nullable', 'array'], 'permissions.*' => [Rule::in(array_keys(self::permissions()))]]);
        $role->fill($data + ['permissions' => []])->save();
        return back()->with('success', 'Đã lưu vai trò. Quyền mới áp dụng cho các nhân viên được gán vai trò này.');
    }

    public function deleteRole(AccessRole $accessRole)
    {
        if (User::where('access_role_id', $accessRole->id)->exists()) return back()->with('error', 'Vai trò đang được gán cho tài khoản. Hãy đổi vai trò tài khoản trước khi xóa.');
        $accessRole->delete();
        return back()->with('success', 'Đã xóa vai trò.');
    }

    public function users(Request $request)
    {
        $users = User::with('accessRole')->when($request->filled('search'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$request->search.'%')->orWhere('email', 'like', '%'.$request->search.'%')->orWhere('phone', 'like', '%'.$request->search.'%')))
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->role))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))->latest()->paginate(15)->withQueryString();
        return view('admin.access.users', compact('users'));
    }

    public function form(?User $user = null)
    {
        return view('admin.access.user-form', ['account' => $user ?? new User, 'roles' => AccessRole::orderBy('name')->get(), 'permissions' => self::permissions()]);
    }

    public function saveUser(Request $request, ?User $user = null)
    {
        $account = $user ?? new User;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($account->id)],
            'phone' => ['nullable', 'string', 'max:20', Rule::unique('users')->ignore($account->id)],
            'password' => [$account->exists ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(['ADMIN', 'EMPLOYEE', 'CUSTOMER'])],
            'status' => ['required', Rule::in(['ACTIVE', 'LOCKED'])],
            'access_role_id' => ['nullable', 'exists:access_roles,id'],
            'permissions' => ['nullable', 'array'], 'permissions.*' => [Rule::in(array_keys(self::permissions()))],
            'customer_segment' => ['required', Rule::in(['NEW', 'REGULAR', 'VIP'])],
        ]);
        if ($account->is($request->user()) && ($data['role'] !== 'ADMIN' || $data['status'] !== 'ACTIVE')) throw ValidationException::withMessages(['role' => 'Không thể tự hạ quyền hoặc khóa tài khoản đang đăng nhập.']);
        if (blank($data['password'] ?? null)) unset($data['password']);
        $data['permissions'] = $data['role'] === 'EMPLOYEE' ? ($data['permissions'] ?? []) : [];
        $data['access_role_id'] = $data['role'] === 'EMPLOYEE' ? ($data['access_role_id'] ?? null) : null;
        DB::transaction(function () use ($account, $data) {
            $admins = User::where('role', 'ADMIN')->where('status', 'ACTIVE')->lockForUpdate()->get();
            if ($account->role === 'ADMIN' && $account->status === 'ACTIVE' && ($data['role'] !== 'ADMIN' || $data['status'] !== 'ACTIVE') && $admins->count() <= 1) throw ValidationException::withMessages(['role' => 'Phải giữ ít nhất một Admin hoạt động.']);
            $account->fill($data)->save();
        });
        return redirect()->route('admin.users.index')->with('success', 'Đã lưu tài khoản và quyền.');
    }

    public function destroyUser(Request $request, User $user)
    {
        abort_if($user->is($request->user()) || $user->role === 'ADMIN', 422, 'Không xóa tài khoản Admin.');
        return DB::transaction(function () use ($user) {
        $user = User::lockForUpdate()->findOrFail($user->id);
        abort_if($user->role === 'ADMIN',422,'Không xóa tài khoản Admin.');
        // Preserve every historical reference, including tables added by other modules.
        foreach (Schema::getTableListing() as $table) {
            foreach (Schema::getForeignKeys($table) as $foreign) {
                if ($foreign['foreign_table'] !== 'users') continue;
                foreach ($foreign['columns'] as $column) {
                    if (DB::table($table)->where($column, $user->id)->exists()) return back()->with('error', 'Tài khoản đã phát sinh dữ liệu. Hãy khóa tài khoản để giữ lịch sử.');
                }
            }
        }
        $user->delete();
        return back()->with('success', 'Đã xóa tài khoản chưa phát sinh dữ liệu.');
        });
    }
}
