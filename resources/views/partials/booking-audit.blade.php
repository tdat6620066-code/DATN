@php
    $actions = ['SPECIAL_REFUND' => 'Hoàn tiền đặc biệt', 'UPDATED' => 'Cập nhật đơn đặt sân', 'CANCELLED' => 'Hủy đơn đặt sân', 'COURT_CHANGED' => 'Chuyển sân', 'CHECKED_IN' => 'Khách đã nhận sân', 'COMPLETED' => 'Hoàn thành lượt chơi', 'SERVICE_ADDED' => 'Thêm dịch vụ', 'SERVICE_REMOVED' => 'Xóa dịch vụ'];
    $statuses = ['PENDING_PAYMENT' => 'Chờ thanh toán', 'CONFIRMED' => 'Đã xác nhận', 'CHECKED_IN' => 'Đang sử dụng sân', 'COMPLETED' => 'Đã hoàn thành', 'CANCELLED' => 'Đã hủy', 'EXPIRED' => 'Đã hết hạn', 'PENDING' => 'Chờ thanh toán', 'PAID' => 'Đã thanh toán', 'REFUNDED' => 'Đã hoàn tiền', 'PARTIALLY_REFUNDED' => 'Đã hoàn một phần', 'FAILED' => 'Thất bại'];
    $fields = ['status' => 'Trạng thái', 'payment_status' => 'Thanh toán', 'refund_amount' => 'Số tiền hoàn', 'note' => 'Ghi chú', 'court' => 'Sân', 'value' => in_array($log->action, ['SERVICE_ADDED', 'SERVICE_REMOVED']) ? 'Dịch vụ' : 'Trạng thái'];
    $before = $log->old_values ?? [];
    $after = $log->new_values ?? [];
    $format = static function ($key, $value) use ($statuses) {
        if ($value === null || $value === '') return '—';
        if ($key === 'refund_amount' && is_numeric($value)) return number_format((float) $value, 0, ',', '.').'đ';
        if (in_array($key, ['status', 'payment_status', 'value']) && is_string($value)) return $statuses[$value] ?? $value;
        return is_scalar($value) ? (string) $value : 'Thông tin đã cập nhật';
    };
@endphp
<article class="audit sz-audit-entry">
    <div class="sz-audit-heading"><strong>{{ $actions[$log->action] ?? 'Cập nhật thông tin đặt sân' }}</strong><time datetime="{{ $log->created_at->toIso8601String() }}">{{ $log->created_at->format('d/m/Y · H:i') }}</time></div>
    <p class="sz-audit-actor">{{ $log->actor?->name ?? 'Hệ thống' }}</p>
    <dl class="sz-audit-changes">
    @foreach(array_unique(array_merge(array_keys($before), array_keys($after))) as $key)
        @if(($before[$key] ?? null) !== ($after[$key] ?? null))
        <div><dt>{{ $fields[$key] ?? 'Thông tin bổ sung' }}</dt><dd>
            @if(array_key_exists($key, $before) && $before[$key] !== null)
            <span class="sz-audit-before">{{ $format($key, $before[$key]) }}</span><span class="sz-audit-arrow" aria-label="chuyển thành">→</span>
            @endif
            <span>{{ $format($key, $after[$key] ?? null) }}</span>
        </dd></div>
        @endif
    @endforeach
    </dl>
    @if($log->reason)<p class="sz-audit-reason"><span>Lý do:</span> {{ $log->reason }}</p>@endif
</article>
