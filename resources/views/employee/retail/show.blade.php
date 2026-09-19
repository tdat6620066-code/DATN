@extends('layouts.employee')
@section('content')
<div class="staff-card p-4"><h1 class="h3">SMASHZONE — Hóa đơn bán lẻ BL-{{ $sale->id }}</h1><p>{{ $sale->created_at->format('d/m/Y H:i') }} · Nhân viên: {{ $sale->employee->name }}</p><p>Khách: {{ $sale->customer_name ?: 'Khách lẻ' }}</p>
<table class="table"><thead><tr><th>Mặt hàng</th><th>Số lượng</th><th>Đơn giá</th><th>Thành tiền</th></tr></thead><tbody>@foreach($sale->lines as $line)<tr><td>{{ $line->name }}</td><td>{{ $line->quantity }}</td><td>{{ number_format($line->unit_price) }}đ</td><td>{{ number_format($line->subtotal) }}đ</td></tr>@endforeach</tbody></table>
<p class="fs-4">Đã thu: <strong>{{ number_format($sale->total) }}đ</strong></p><p>{{ ['CASH'=>'Tiền mặt','BANK_TRANSFER'=>'Chuyển khoản','QR'=>'QR'][$sale->payment_method] }} {{ $sale->transaction_id }}</p>
<div class="d-print-none"><button type="button" onclick="window.print()" class="staff-button">In hóa đơn</button> <a href="{{ route('employee.retail.index') }}" class="staff-button">Bán tiếp</a></div></div>
@endsection
@push('styles')<style>@media print{.staff-sidebar,.staff-topbar{display:none!important}.staff-content{margin:0}.staff-main{padding:0}.staff-card{box-shadow:none;border:0}}</style>@endpush
