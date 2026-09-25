@if(auth()->user()->hasPermission('incidents.manage') && in_array($booking->status, ['PENDING_PAYMENT','CONFIRMED']))
<div class="staff-card p-4 mt-3"><h2 class="h5">Hỗ trợ hủy booking</h2><p>Chỉ hủy đơn chưa thanh toán; đơn đã trả tiền cần xử lý theo chính sách sự cố / hoàn tiền.</p>
</select></label></div><div class="col-md-4"><label class="form-label">Lý do đổi<input name="reason" class="form-control" required maxlength="1000"></label></div></div><button class="staff-button">Kiểm tra và đổi lịch</button></form>
@endforeach
@if($booking->status==='PENDING_PAYMENT')<form method="POST" action="{{ route('employee.bookings.cancel',$booking) }}">@csrf @method('PUT')<label class="form-label d-block">Lý do hủy<input name="reason" class="form-control" required maxlength="1000"></label><button class="staff-button">Hủy đơn chưa thanh toán</button></form>@endif
</div>
@endif
