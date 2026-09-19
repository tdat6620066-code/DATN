@extends('layouts.admin')
@section('title','Thống kê trợ lý AI - SmashZone')
@section('page_heading','Thống kê trợ lý AI')
@section('content')<x-admin.workspace>
@php
    $modeLabels = ['offline' => 'Ngoại tuyến', 'online' => 'Trực tuyến', 'live' => 'Trực tuyến'];
    $statusLabels = ['PASSED' => 'Đạt', 'FAILED' => 'Không đạt', 'RUNNING' => 'Đang chạy', 'PENDING' => 'Chờ chạy', 'ERROR' => 'Lỗi'];
    $categoryLabels = ['intent' => 'Nhận diện yêu cầu', 'privacy' => 'Quyền riêng tư', 'multi_intent' => 'Xử lý nhiều yêu cầu', 'normal_safety' => 'An toàn hội thoại', 'prompt_injection' => 'Chống chèn lệnh'];
    $intentLabels = ['FAQ' => 'Câu hỏi thường gặp', 'UNKNOWN' => 'Chưa xác định', 'CANCEL_BOOKING' => 'Hủy đặt sân', 'PAYMENT_STATUS' => 'Trạng thái thanh toán', 'BOOK_COURT' => 'Đặt sân', 'CHECK_AVAILABILITY' => 'Kiểm tra sân trống', 'COURT_PRICE' => 'Giá thuê sân', 'BOOKING_STATUS' => 'Trạng thái đặt sân', 'PROMOTION' => 'Khuyến mãi', 'SERVICE' => 'Dịch vụ', 'FIND_COURT' => 'Tìm sân', 'MULTI_INTENT_BOOKING' => 'Đặt sân với nhiều yêu cầu'];
    $engineLabels = ['UNKNOWN' => 'Chưa xác định', 'local' => 'Xử lý nội bộ', 'local-fallback' => 'Xử lý dự phòng nội bộ', 'local-planner' => 'Lập kế hoạch nội bộ', 'planner' => 'Lập kế hoạch', 'faq' => 'Tra cứu câu hỏi thường gặp', 'fallback' => 'Xử lý dự phòng', 'openai' => 'OpenAI', 'rule' => 'Xử lý theo quy tắc', 'rules' => 'Xử lý theo quy tắc'];
@endphp
@php
    $intentLabels += ['RECOMMEND_COURT' => 'Gợi ý sân phù hợp', 'TOOL_AGENT' => 'Trợ lý xử lý yêu cầu', 'find_available_courts' => 'Tìm sân trống', 'ask_booking_date' => 'Hỏi ngày đặt sân', 'expired_slot' => 'Khung giờ hết hiệu lực', 'confirm_booking' => 'Xác nhận đặt sân', 'booking_confirmed' => 'Đặt sân thành công', 'find_other_slot' => 'Tìm khung giờ khác'];
    $engineLabels += ['database' => 'Tra cứu cơ sở dữ liệu', 'knowledge-v3' => 'Tra cứu kho kiến thức (bản 3)', 'recommendation-v1' => 'Gợi ý sân (bản 1)', 'booking-copilot-v1' => 'Hỗ trợ đặt sân (bản 1)', 'security-guard' => 'Kiểm tra an toàn'];
@endphp
<style>
.ai-kpi{border:0;border-radius:16px;box-shadow:0 7px 24px rgba(10,49,58,.07)}.ai-kpi .value{font-size:1.75rem;font-weight:800;color:#073b45}.ai-panel{border:0;border-radius:18px;box-shadow:0 7px 24px rgba(10,49,58,.07)}.ai-chart{height:300px}.ai-rank{width:30px;height:30px;display:grid;place-items:center;border-radius:9px;background:#e6f8f1;color:#07845d;font-weight:800}.ai-error{font-family:monospace;font-size:.78rem}
</style>
@if($latestEval)
<div class="card ai-panel mb-4 border-start border-4 {{ $latestEval->status === 'PASSED' ? 'border-success' : 'border-danger' }}"><div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3"><div><small class="text-muted">ĐIỂM CHẤT LƯỢNG MỚI NHẤT · {{ $latestEval->version }}</small><div class="display-5 fw-bold {{ $latestEval->status === 'PASSED' ? 'text-success' : 'text-danger' }}">{{ number_format($latestEval->quality_score,1) }}%</div><span>{{ $latestEval->passed }}/{{ $latestEval->total }} tình huống đạt · {{ $modeLabels[$latestEval->mode] ?? 'Chưa xác định' }}</span></div><div class="d-flex flex-wrap gap-2">@foreach(($latestEval->category_scores ?? []) as $category=>$score)<span class="badge rounded-pill text-bg-light border p-2">{{ $categoryLabels[$category] ?? 'Nhóm khác' }}: {{ $score }}%</span>@endforeach</div></div></div>
@else
<div class="alert alert-warning mb-4">Chưa có kết quả kiểm thử AI. Chạy <code>php artisan chatbot:eval --release=v1</code> trước khi triển khai.</div>
@endif
<div class="card ai-panel mb-4"><div class="card-body"><h2 class="h5">Lịch sử chất lượng theo phiên bản</h2><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Phiên bản</th><th>Chế độ</th><th>Điểm</th><th>Kết quả</th><th>Đạt</th><th>Thời gian chạy</th><th>Ngày chạy</th></tr></thead><tbody>@forelse($evalRuns as $run)<tr><td><strong>{{ $run->version }}</strong></td><td>{{ $modeLabels[$run->mode] ?? 'Chưa xác định' }}</td><td><strong>{{ number_format($run->quality_score,1) }}%</strong></td><td><span class="badge {{ $run->status === 'PASSED' ? 'text-bg-success' : ($run->status === 'RUNNING' ? 'text-bg-info' : 'text-bg-danger') }}">{{ $statusLabels[$run->status] ?? 'Chưa xác định' }}</span></td><td>{{ $run->passed }}/{{ $run->total }}</td><td>{{ $run->duration_ms ? number_format($run->duration_ms).' ms' : '—' }}</td><td>{{ $run->created_at->format('d/m/Y H:i') }}</td></tr>@empty<tr><td colspan="7" class="text-center text-muted"><x-admin.empty message="Chưa có phiên kiểm thử." /></td></tr>@endforelse</tbody></table></div></div></div>
<div class="d-flex justify-content-between align-items-center mb-4"><div><x-admin.page-heading>Thống kê trợ lý SmashBot</x-admin.page-heading><p class="text-muted mb-0">Chất lượng hội thoại, hiệu năng và giá trị kinh doanh do chatbot tạo ra.</p></div><x-admin.filters><select name="days" class="form-select" onchange="this.form.submit()">@foreach([7,30,90] as $option)<option value="{{ $option }}" @selected($days===$option)>{{ $option }} ngày</option>@endforeach</select></x-admin.filters></div>
<div class="row g-3 mb-4">
@foreach([
 ['Hội thoại',$summary['total'],'bi-chat-dots'],
 ['Phản hồi tích cực',$summary['positive_rate'].'%','bi-hand-thumbs-up'],
 ['Độ trễ trung bình',$summary['avg_latency'].' ms','bi-speedometer2'],
 ['Lỗi OpenAI',$summary['openai_errors'],'bi-exclamation-triangle'],
 ['Đơn đặt sân từ trợ lý',$summary['chatbot_bookings'],'bi-calendar-check'],
 ['Giá trị đơn đặt sân',number_format($summary['booking_value']).'đ','bi-receipt'],
 ['Tổng thanh toán trong kỳ',number_format($summary['chatbot_gross_revenue']).'đ','bi-credit-card'],
 ['Đã hoàn trong kỳ',number_format($summary['chatbot_refund_amount']).'đ','bi-arrow-return-left'],
 ['Doanh thu thực nhận trong kỳ',number_format($summary['chatbot_revenue']).'đ','bi-cash-coin'],
 ['Trợ lý chưa hiểu',$summary['unanswered_rate'].'%','bi-question-circle']
] as [$label,$value,$icon])
<div class="col-6 col-xl-3"><div class="card ai-kpi h-100"><div class="card-body"><div class="d-flex justify-content-between"><small class="text-muted">{{ $label }}</small><i class="bi {{ $icon }} text-success"></i></div><div class="value mt-2">{{ $value }}</div></div></div></div>
@endforeach
</div>
<div class="row g-3 mb-4">
 <div class="col-xl-8"><div class="card ai-panel h-100"><div class="card-body"><h2 class="h5">Hội thoại và thời gian phản hồi</h2><div class="ai-chart"><canvas id="conversationChart"></canvas></div></div></div></div>
 <div class="col-xl-4"><div class="card ai-panel h-100"><div class="card-body"><h2 class="h5">Tỷ lệ đánh giá</h2><div class="ai-chart"><canvas id="feedbackChart"></canvas></div><div class="text-center text-muted small">👍 {{ $summary['positive_feedback'] }} · 👎 {{ $summary['negative_feedback'] }}</div></div></div></div>
</div>
<div class="row g-3 mb-4">
 <div class="col-xl-6"><div class="card ai-panel h-100"><div class="card-body"><h2 class="h5">Câu hỏi phổ biến nhất</h2>@forelse($topQuestions as $item)<div class="d-flex align-items-center gap-3 border-bottom py-2"><span class="ai-rank">{{ $loop->iteration }}</span><span class="flex-grow-1">{{ Str::limit($item->question,90) }}</span><strong>{{ $item->total }}</strong></div>@empty<p class="text-muted">Chưa có dữ liệu.</p>@endforelse</div></div></div>
 <div class="col-xl-3"><div class="card ai-panel h-100"><div class="card-body"><h2 class="h5">Yêu cầu phổ biến</h2>@forelse($intents as $item)<div class="d-flex justify-content-between border-bottom py-2"><span>{{ $intentLabels[$item->intent] ?? 'Yêu cầu khác' }}</span><strong>{{ $item->total }}</strong></div>@empty<p class="text-muted">Chưa có dữ liệu.</p>@endforelse</div></div></div>
 <div class="col-xl-3"><div class="card ai-panel h-100"><div class="card-body"><h2 class="h5">Bộ xử lý</h2>@forelse($engines as $item)<div class="d-flex justify-content-between border-bottom py-2"><span>{{ $engineLabels[$item->engine] ?? ($item->engine ?: 'Chưa xác định') }}</span><strong>{{ $item->total }}</strong></div>@empty<p class="text-muted">Chưa có dữ liệu.</p>@endforelse</div></div></div>
</div>
<div class="card ai-panel mb-4"><div class="card-body"><h2 class="h5">Lỗi OpenAI gần đây</h2><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Loại lỗi</th><th>Câu hỏi</th><th>Xử lý dự phòng</th><th>Độ trễ</th><th>Thời gian</th></tr></thead><tbody>@forelse($openAiErrors as $log)<tr><td><span class="badge text-bg-warning ai-error">{{ data_get($log->metadata,'openai_error') }}</span></td><td>{{ Str::limit($log->question,80) }}</td><td>{{ data_get($log->metadata,'fallback') ? 'Có' : 'Không' }}</td><td>{{ $log->latency_ms }} ms</td><td>{{ $log->created_at->format('d/m H:i') }}</td></tr>@empty<tr><td colspan="5" class="text-center text-muted"><x-admin.empty message="Không ghi nhận lỗi OpenAI trong kỳ." /></td></tr>@endforelse</tbody></table></div></div></div>
<div class="card ai-panel mb-4"><div class="card-body"><h2 class="h5">Phản hồi cần xem lại</h2><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Khách</th><th>Câu hỏi</th><th>Câu trả lời</th><th>Góp ý</th></tr></thead><tbody>@forelse($negativeFeedback as $feedback)<tr><td>{{ $feedback->chatbotLog?->user?->name ?: 'Ẩn danh' }}</td><td>{{ Str::limit($feedback->chatbotLog?->question,70) }}</td><td>{{ Str::limit($feedback->chatbotLog?->answer,90) }}</td><td>{{ $feedback->comment ?: 'Không có ghi chú' }}</td></tr>@empty<tr><td colspan="4" class="text-center text-muted"><x-admin.empty message="Chưa có phản hồi tiêu cực." /></td></tr>@endforelse</tbody></table></div></div></div>
<div class="card ai-panel"><div class="card-body"><h2 class="h5">Câu hỏi trợ lý chưa hiểu</h2>@forelse($unanswered as $item)<form method="POST" action="{{ route('admin.chatbot-analytics.resolve',$item) }}" class="border-bottom py-3">@csrf<div class="d-flex justify-content-between"><strong>{{ $item->question }}</strong><span>{{ $item->occurrences }} lượt</span></div><div class="row g-2 mt-2"><div class="col-md-3"><input class="form-control" name="category" placeholder="Danh mục"></div><div class="col-md-7"><input class="form-control" name="answer" required placeholder="Câu trả lời chuẩn để thêm vào mục hỏi đáp"></div><div class="col-md-2"><button class="btn btn-success w-100">Bổ sung</button></div></div></form>@empty<p class="text-muted mb-0">Không có câu hỏi tồn đọng.</p>@endforelse</div></div>
</x-admin.workspace>@endsection
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
const aiChart=@json($chart);
new Chart(document.getElementById('conversationChart'),{type:'bar',data:{labels:aiChart.labels,datasets:[{label:'Hội thoại',data:aiChart.conversations,backgroundColor:'#19b97b',borderRadius:5,yAxisID:'y'},{label:'Độ trễ (ms)',data:aiChart.latency,type:'line',borderColor:'#0a5266',tension:.35,yAxisID:'latency'}]},options:{responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false},plugins:{legend:{position:'bottom'}},scales:{y:{beginAtZero:true,ticks:{precision:0}},latency:{position:'right',beginAtZero:true,grid:{display:false}}}}});
new Chart(document.getElementById('feedbackChart'),{type:'doughnut',data:{labels:['Tích cực','Tiêu cực'],datasets:[{data:aiChart.feedback,backgroundColor:['#19b97b','#ef6b6b'],borderWidth:0}]},options:{responsive:true,maintainAspectRatio:false,cutout:'68%',plugins:{legend:{position:'bottom'}}}});
</script>
@endpush
