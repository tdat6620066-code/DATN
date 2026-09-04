@extends('layouts.admin')
@section('title','AI Dashboard - SmashZone')
@section('page_heading','AI Dashboard')
@section('content')
<style>
.ai-kpi{border:0;border-radius:16px;box-shadow:0 7px 24px rgba(10,49,58,.07)}.ai-kpi .value{font-size:1.75rem;font-weight:800;color:#073b45}.ai-panel{border:0;border-radius:18px;box-shadow:0 7px 24px rgba(10,49,58,.07)}.ai-chart{height:300px}.ai-rank{width:30px;height:30px;display:grid;place-items:center;border-radius:9px;background:#e6f8f1;color:#07845d;font-weight:800}.ai-error{font-family:monospace;font-size:.78rem}
</style>
@if($latestEval)
<div class="card ai-panel mb-4 border-start border-4 {{ $latestEval->status === 'PASSED' ? 'border-success' : 'border-danger' }}"><div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3"><div><small class="text-muted">QUALITY SCORE MỚI NHẤT · {{ $latestEval->version }}</small><div class="display-5 fw-bold {{ $latestEval->status === 'PASSED' ? 'text-success' : 'text-danger' }}">{{ number_format($latestEval->quality_score,1) }}%</div><span>{{ $latestEval->passed }}/{{ $latestEval->total }} tình huống đạt · {{ $latestEval->mode }}</span></div><div class="d-flex flex-wrap gap-2">@foreach(($latestEval->category_scores ?? []) as $category=>$score)<span class="badge rounded-pill text-bg-light border p-2">{{ $category }}: {{ $score }}%</span>@endforeach</div></div></div>
@else
<div class="alert alert-warning mb-4">Chưa có kết quả kiểm thử AI. Chạy <code>php artisan chatbot:eval --release=v1</code> trước khi deploy.</div>
@endif
<div class="card ai-panel mb-4"><div class="card-body"><h2 class="h5">Lịch sử chất lượng theo phiên bản</h2><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Phiên bản</th><th>Chế độ</th><th>Điểm</th><th>Kết quả</th><th>Đạt</th><th>Thời gian chạy</th><th>Ngày chạy</th></tr></thead><tbody>@forelse($evalRuns as $run)<tr><td><strong>{{ $run->version }}</strong></td><td>{{ $run->mode }}</td><td><strong>{{ number_format($run->quality_score,1) }}%</strong></td><td><span class="badge {{ $run->status === 'PASSED' ? 'text-bg-success' : ($run->status === 'RUNNING' ? 'text-bg-info' : 'text-bg-danger') }}">{{ $run->status }}</span></td><td>{{ $run->passed }}/{{ $run->total }}</td><td>{{ $run->duration_ms ? number_format($run->duration_ms).' ms' : '—' }}</td><td>{{ $run->created_at->format('d/m/Y H:i') }}</td></tr>@empty<tr><td colspan="7" class="text-center text-muted">Chưa có phiên kiểm thử.</td></tr>@endforelse</tbody></table></div></div></div>
<div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="h3 mb-1">SmashBot AI Dashboard</h1><p class="text-muted mb-0">Chất lượng hội thoại, hiệu năng và giá trị kinh doanh do chatbot tạo ra.</p></div><form><select name="days" class="form-select" onchange="this.form.submit()">@foreach([7,30,90] as $option)<option value="{{ $option }}" @selected($days===$option)>{{ $option }} ngày</option>@endforeach</select></form></div>
<div class="row g-3 mb-4">
@foreach([
 ['Hội thoại',$summary['total'],'bi-chat-dots'],
 ['Phản hồi tích cực',$summary['positive_rate'].'%','bi-hand-thumbs-up'],
 ['Độ trễ trung bình',$summary['avg_latency'].' ms','bi-speedometer2'],
 ['Lỗi OpenAI',$summary['openai_errors'],'bi-exclamation-triangle'],
 ['Booking từ chatbot',$summary['chatbot_bookings'],'bi-calendar-check'],
 ['Giá trị booking',number_format($summary['booking_value']).'đ','bi-receipt'],
 ['Doanh thu đã thanh toán',number_format($summary['chatbot_revenue']).'đ','bi-cash-coin'],
 ['Bot chưa hiểu',$summary['unanswered_rate'].'%','bi-question-circle']
] as [$label,$value,$icon])
<div class="col-6 col-xl-3"><div class="card ai-kpi h-100"><div class="card-body"><div class="d-flex justify-content-between"><small class="text-muted">{{ $label }}</small><i class="bi {{ $icon }} text-success"></i></div><div class="value mt-2">{{ $value }}</div></div></div></div>
@endforeach
</div>
<div class="row g-3 mb-4">
 <div class="col-xl-8"><div class="card ai-panel h-100"><div class="card-body"><h2 class="h5">Hội thoại và thời gian phản hồi</h2><div class="ai-chart"><canvas id="conversationChart"></canvas></div></div></div></div>
 <div class="col-xl-4"><div class="card ai-panel h-100"><div class="card-body"><h2 class="h5">Tỷ lệ đánh giá</h2><div class="ai-chart"><canvas id="feedbackChart"></canvas></div><div class="text-center text-muted small">👍 {{ $summary['positive_feedback'] }} · 👎 {{ $summary['negative_feedback'] }}</div></div></div></div>
</div>
<div class="row g-3 mb-4">
 <div class="col-xl-6"><div class="card ai-panel h-100"><div class="card-body"><h2 class="h5">Top câu hỏi</h2>@forelse($topQuestions as $item)<div class="d-flex align-items-center gap-3 border-bottom py-2"><span class="ai-rank">{{ $loop->iteration }}</span><span class="flex-grow-1">{{ Str::limit($item->question,90) }}</span><strong>{{ $item->total }}</strong></div>@empty<p class="text-muted">Chưa có dữ liệu.</p>@endforelse</div></div></div>
 <div class="col-xl-3"><div class="card ai-panel h-100"><div class="card-body"><h2 class="h5">Intent phổ biến</h2>@forelse($intents as $item)<div class="d-flex justify-content-between border-bottom py-2"><span>{{ $item->intent ?: 'UNKNOWN' }}</span><strong>{{ $item->total }}</strong></div>@empty<p class="text-muted">Chưa có dữ liệu.</p>@endforelse</div></div></div>
 <div class="col-xl-3"><div class="card ai-panel h-100"><div class="card-body"><h2 class="h5">Engine xử lý</h2>@forelse($engines as $item)<div class="d-flex justify-content-between border-bottom py-2"><span>{{ $item->engine ?: 'UNKNOWN' }}</span><strong>{{ $item->total }}</strong></div>@empty<p class="text-muted">Chưa có dữ liệu.</p>@endforelse</div></div></div>
</div>
<div class="card ai-panel mb-4"><div class="card-body"><h2 class="h5">Lỗi OpenAI gần đây</h2><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Loại lỗi</th><th>Câu hỏi</th><th>Fallback</th><th>Độ trễ</th><th>Thời gian</th></tr></thead><tbody>@forelse($openAiErrors as $log)<tr><td><span class="badge text-bg-warning ai-error">{{ data_get($log->metadata,'openai_error') }}</span></td><td>{{ Str::limit($log->question,80) }}</td><td>{{ data_get($log->metadata,'fallback') ? 'Có' : 'Không' }}</td><td>{{ $log->latency_ms }} ms</td><td>{{ $log->created_at->format('d/m H:i') }}</td></tr>@empty<tr><td colspan="5" class="text-center text-muted">Không ghi nhận lỗi OpenAI trong kỳ.</td></tr>@endforelse</tbody></table></div></div></div>
<div class="card ai-panel mb-4"><div class="card-body"><h2 class="h5">Phản hồi cần xem lại</h2><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Khách</th><th>Câu hỏi</th><th>Câu trả lời</th><th>Góp ý</th></tr></thead><tbody>@forelse($negativeFeedback as $feedback)<tr><td>{{ $feedback->chatbotLog?->user?->name ?: 'Ẩn danh' }}</td><td>{{ Str::limit($feedback->chatbotLog?->question,70) }}</td><td>{{ Str::limit($feedback->chatbotLog?->answer,90) }}</td><td>{{ $feedback->comment ?: 'Không có ghi chú' }}</td></tr>@empty<tr><td colspan="4" class="text-center text-muted">Chưa có phản hồi tiêu cực.</td></tr>@endforelse</tbody></table></div></div></div>
<div class="card ai-panel"><div class="card-body"><h2 class="h5">Câu bot chưa hiểu</h2>@forelse($unanswered as $item)<form method="POST" action="{{ route('admin.chatbot-analytics.resolve',$item) }}" class="border-bottom py-3">@csrf<div class="d-flex justify-content-between"><strong>{{ $item->question }}</strong><span>{{ $item->occurrences }} lượt</span></div><div class="row g-2 mt-2"><div class="col-md-3"><input class="form-control" name="category" placeholder="Danh mục"></div><div class="col-md-7"><input class="form-control" name="answer" required placeholder="Câu trả lời chuẩn để thêm vào FAQ"></div><div class="col-md-2"><button class="btn btn-success w-100">Bổ sung</button></div></div></form>@empty<p class="text-muted mb-0">Không có câu hỏi tồn đọng.</p>@endforelse</div></div>
@endsection
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
const aiChart=@json($chart);
new Chart(document.getElementById('conversationChart'),{type:'bar',data:{labels:aiChart.labels,datasets:[{label:'Hội thoại',data:aiChart.conversations,backgroundColor:'#19b97b',borderRadius:5,yAxisID:'y'},{label:'Độ trễ (ms)',data:aiChart.latency,type:'line',borderColor:'#0a5266',tension:.35,yAxisID:'latency'}]},options:{responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false},plugins:{legend:{position:'bottom'}},scales:{y:{beginAtZero:true,ticks:{precision:0}},latency:{position:'right',beginAtZero:true,grid:{display:false}}}}});
new Chart(document.getElementById('feedbackChart'),{type:'doughnut',data:{labels:['Tích cực','Tiêu cực'],datasets:[{data:aiChart.feedback,backgroundColor:['#19b97b','#ef6b6b'],borderWidth:0}]},options:{responsive:true,maintainAspectRatio:false,cutout:'68%',plugins:{legend:{position:'bottom'}}}});
</script>
@endpush
