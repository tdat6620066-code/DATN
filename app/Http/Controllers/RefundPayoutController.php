<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\RefundRequest;
use App\Services\RefundRecipientService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefundPayoutController extends Controller
{
    public function receipt(Request $request, \App\Models\Refund $refund)
    {
        $user = $request->user();
        $owner = $user->role === 'CUSTOMER' && $refund->refundRequest->booking->user_id === $user->id;
        $staff = in_array($user->role, ['ADMIN', 'EMPLOYEE'], true)
            && ($user->hasPermission('refunds.process') || $user->hasPermission('incidents.manage'));
        abort_unless($owner || $staff, 403);
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        abort_unless($refund->status === 'COMPLETED' && $refund->receipt_path && $disk->exists($refund->receipt_path), 404);
        return $disk->response($refund->receipt_path, 'refund-'.$refund->id.'.'.pathinfo($refund->receipt_path, PATHINFO_EXTENSION),
            ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'], 'inline');
    }

    public function index()
    {
        $items = RefundRequest::with('booking.user')->where('status', 'APPROVED')->whereDoesntHave('refund')->latest()->orderByDesc('id')->paginate(20);

        return view('refund-payouts.index', compact('items'));
    }

    public function show(RefundRequest $refundRequest)
    {
        app(RefundRecipientService::class)->restoreFromBookingTicket($refundRequest);
        $refundRequest->load('booking.user', 'booking.payment', 'refund');

        return response()->view('refund-payouts.show', ['item' => $refundRequest])->header('Cache-Control', 'private, no-store');
    }

    public function recipient(Request $request, RefundRequest $refundRequest)
    {
        abort_unless($refundRequest->booking->user_id === $request->user()->id, 403);
        // Never flash bank details into the session on validation errors.
        $validator = validator($request->all(), RefundRecipientService::rules());
        if ($validator->fails()) {
            return back()->withErrors($validator);
        }
        $data = $validator->validated();
        try {
            DB::transaction(function () use ($refundRequest, $data) {
                Booking::lockForUpdate()->findOrFail($refundRequest->booking_id);
                $item = RefundRequest::lockForUpdate()->findOrFail($refundRequest->id);
                if (! in_array($item->status, ['PENDING', 'APPROVED', 'NEEDS_INFO'], true) || $item->processing_started_at || $item->refund()->exists()) {
                    throw ValidationException::withMessages(['bank_account_number' => 'Yêu cầu đã khóa thông tin nhận tiền hoặc đã xử lý.']);
                }
                app(RefundRecipientService::class)->save($item, $data);
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Đã lưu thông tin nhận tiền. Chỉ nhân sự có quyền xử lý hoàn tiền được xem đầy đủ.');
    }

    public function confirmRecipient(Request $request, RefundRequest $refundRequest)
    {
        abort_unless($refundRequest->booking->user_id === $request->user()->id, 403);
        $data = $request->validate(['recipient_confirmed' => ['accepted'], 'account_version' => ['required', 'string']]);
        DB::transaction(function () use ($refundRequest, $data) {
            Booking::lockForUpdate()->findOrFail($refundRequest->booking_id);
            $item = RefundRequest::lockForUpdate()->findOrFail($refundRequest->id);
            $bank = $item->bankAccount()->lockForUpdate()->first();
            if (! in_array($item->status, ['PENDING', 'APPROVED', 'NEEDS_INFO'], true) || $item->processing_started_at || $item->refund()->exists()) {
                throw ValidationException::withMessages(['recipient_confirmed' => 'Thông tin nhận tiền đã khóa khi bắt đầu xử lý.']);
            }
            if (! $bank || ! hash_equals(hash('sha256', $bank->getRawOriginal('account_number')), $data['account_version'])) {
                throw ValidationException::withMessages(['recipient_confirmed' => 'Thông tin tài khoản đã thay đổi. Vui lòng tải lại trang để kiểm tra.']);
            }
            $bank->update(['confirmed_at' => now()]);
        });
        return back()->with('success', 'Đã xác nhận lại thông tin tài khoản nhận hoàn tiền.');
    }
}
