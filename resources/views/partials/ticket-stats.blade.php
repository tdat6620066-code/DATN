@php($ticketCounts = \App\Models\CourtIncident::where('source','CUSTOMER')->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total','status'))
<section class="card p-3 mb-4"><h2 class="h5"><a href="{{ route('incident-tickets.index') }}">⚠ Yêu cầu xử lý sự cố</a></h2><div class="row">
@foreach(['PENDING'=>'Chờ xử lý','REVIEWING'=>'Đang xem xét','NEED_MORE_INFO'=>'Cần khách bổ sung'] as $status=>$label)<div class="col-md-3">{{ $label }}<strong class="d-block fs-3">{{ $ticketCounts[$status] ?? 0 }}</strong></div>@endforeach
<div class="col-md-3">Đã xử lý hôm nay<strong class="d-block fs-3">{{ \App\Models\CourtIncident::where('source','CUSTOMER')->whereIn('status',['RESOLVED','REJECTED'])->whereDate('resolved_at',today())->count() }}</strong></div>
</div></section>
