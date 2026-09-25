<div class="text-nowrap">Tiền gốc: {{ number_format($payment->amount,0,',','.') }}đ</div>
<div class="text-danger text-nowrap">Đã hoàn: {{ number_format($payment->completed_refund_amount ?? 0,0,',','.') }}đ</div>
<strong class="d-block text-success text-nowrap">Còn lại: {{ number_format(($payment->status === 'PAID' ? (float) $payment->amount : 0) - (float) ($payment->completed_refund_amount ?? 0),0,',','.') }}đ</strong>
<small class="text-muted">{{ ($payment->completed_refund_amount ?? 0) > 0 ? ((float) $payment->completed_refund_amount >= (float) $payment->amount ? 'Đã hoàn toàn bộ' : 'Đã hoàn một phần') : ($payment->status === 'PAID' ? 'Đã thu, chưa hoàn' : 'Chưa thu thành công') }}</small>
