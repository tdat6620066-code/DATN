<?php
namespace App\Http\Controllers;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class AdminSettingsController extends Controller
{
    public function edit()
    {
        return view('admin.settings', ['settings' => SystemSetting::pluck('value', 'key')]);
    }
    public function update(Request $request)
    {
        $data = $request->validate([
            'site_name' => ['required', 'string', 'max:100'], 'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:20'], 'email' => ['nullable', 'email', 'max:255'],
            'vnpay_enabled' => ['required', 'boolean'], 'cash_enabled' => ['required', 'boolean'],
        ]);
        if (! $data['vnpay_enabled'] && ! $data['cash_enabled']) return back()->withInput()->with('error', 'Cần bật ít nhất một phương thức thanh toán.');
        DB::transaction(function () use ($data) { foreach ($data as $key => $value) SystemSetting::updateOrCreate(['key' => $key], ['value' => (string) $value]); });
        return back()->with('success', 'Đã lưu cài đặt hệ thống.');
    }
}
