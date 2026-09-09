@extends(auth()->user()->role === 'ADMIN' ? 'layouts.admin' : 'layouts.employee')
@section('page_heading','Xử lý hoàn tiền đã được duyệt')
@section('content')
@include('partials.incident-ui')
@if(auth()->user()->role === 'ADMIN')
<p><a class="sz-action" href="{{ route('special-refunds.index') }}">Duyệt yêu cầu hoàn tiền đang chờ</a></p>
@endif
<div class="card p-3"><table class="table"><thead><tr><th>Booking</th><th>Khách</th><th>Số tiền</th><th></th></tr></thead><tbody>@forelse($items as $item)<tr><td>{{ $item->booking->booking_code }}</td><td>{{ $item->booking->user->name }}</td><td>{{ number_format($item->amount) }}đ</td><td><a class="sz-action sz-action--small" href="{{ route('refund-payouts.show',$item) }}"><i class="bi bi-arrow-up-right" aria-hidden="true"></i>Chi trả #{{ $item->id }}</a></td></tr>@empty<tr><td colspan="4">Không có khoản hoàn đang chờ.</td></tr>@endforelse</tbody></table>{{ $items->links() }}</div>
@endsection
