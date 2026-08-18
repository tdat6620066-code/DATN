<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminVoucherController extends Controller
{
    public function index(Request $request)
    {
        $this->admin($request);
        $vouchers = Voucher::when($request->filled('search'), fn ($q) => $q->where('code', 'like', '%'.$request->search.'%')->orWhere('name', 'like', '%'.$request->search.'%'))->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))->latest()->paginate(15)->withQueryString();

        return view('admin.vouchers.index', compact('vouchers'));
    }

    public function create(Request $request)
    {
        $this->admin($request);

        return view('admin.vouchers.form', ['voucher' => null]);
    }

    public function store(Request $request)
    {
        $this->admin($request);
        $data = $this->validated($request);
        $data['code'] = strtoupper($data['code']);
        $data['used_count'] = 0;
        Voucher::create($data);

        return redirect()->route('admin.vouchers.index')->with('success', 'Đã tạo voucher.');
    }

    public function edit(Voucher $voucher, Request $request)
    {
        $this->admin($request);

        return view('admin.vouchers.form', compact('voucher'));
    }

    public function update(Voucher $voucher, Request $request)
    {
        $this->admin($request);
        $data = $this->validated($request, $voucher);
        $data['code'] = strtoupper($data['code']);
        $voucher->update($data);

        return redirect()->route('admin.vouchers.index')->with('success', 'Đã cập nhật voucher.');
    }

    public function toggle(Voucher $voucher, Request $request)
    {
        $this->admin($request);
        $voucher->update(['status' => $voucher->status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE']);

        return back()->with('success', 'Đã cập nhật trạng thái voucher.');
    }

    public function destroy(Voucher $voucher, Request $request)
    {
        $this->admin($request);
        if ($voucher->used_count > 0) {
            $voucher->update(['status' => 'INACTIVE']);

            return back()->with('error', 'Voucher đã phát sinh lượt dùng nên được chuyển sang ngừng hoạt động.');
        }$voucher->delete();

        return back()->with('success', 'Đã xóa voucher.');
    }

    private function validated(Request $request, ?Voucher $voucher = null): array
    {
        $rules = ['code' => ['required', 'string', 'max:50', Rule::unique('vouchers', 'code')->ignore($voucher)], 'name' => ['required', 'string', 'max:255'], 'discount_type' => ['required', Rule::in(['FIXED', 'PERCENTAGE'])], 'discount_value' => ['required', 'numeric', 'gt:0'], 'min_order_amount' => ['required', 'numeric', 'min:0'], 'max_discount' => ['nullable', 'numeric', 'gt:0'], 'start_at' => ['required', 'date'], 'end_at' => ['required', 'date', 'after:start_at'], 'usage_limit' => ['nullable', 'integer', 'min:1'], 'conditions' => ['nullable', 'string', 'max:3000'], 'status' => ['required', Rule::in(['ACTIVE', 'INACTIVE'])]];
        $rules['discount_value'][] = Rule::when($request->discount_type === 'PERCENTAGE', ['max:100']);
        $data = $request->validate($rules, [
            'status.required' => 'Vui lòng chọn trạng thái voucher.',
            'status.in' => 'Trạng thái voucher không hợp lệ.',
            'code.unique' => 'Mã voucher đã tồn tại.',
            'end_at.after' => 'Thời gian kết thúc phải sau thời gian bắt đầu.',
            'discount_value.max' => 'Mức giảm phần trăm không được vượt quá 100%.',
        ]);
        if ($data['discount_type'] === 'PERCENTAGE' && (float) $data['discount_value'] > 100) {
            throw ValidationException::withMessages(['discount_value' => 'Giảm phần trăm không được vượt quá 100%.']);
        }

return $data;
    }

    private function admin(Request $request): void
    {
        abort_unless($request->user()->role === 'ADMIN',403);
    }
}
