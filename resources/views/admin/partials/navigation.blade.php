@php
    $navigation = [
        ['Dashboard','grid-1x2','admin.dashboard',[]],
        ['Sân cầu lông','grid-3x3-gap','admin.courts.index',[]],
        ['Khung giờ','clock','admin.slots.index',[]],
        ['Bảng giá','cash-coin','admin.pricing.index',[]],
        ['Booking','calendar-check','admin.bookings.index',[]],
        ['Booking cố định','calendar-week','admin.bookings.index',['fixed'=>1]],
        ['Khách hàng','people','admin.customers.index',[]],
        ['Nhân viên','person-badge','admin.employees.index',[]],
        ['Dịch vụ','bag','admin.content.index',['kind'=>'services']],
        ['Khuyến mãi','ticket-perforated','admin.vouchers.index',[]],
        ['Thanh toán','credit-card','admin.payments.index',[]],
        ['Hoàn tiền','arrow-counterclockwise','special-refunds.index',[]],
        ['Chi trả hoàn tiền','wallet2','refund-payouts.index',[]],
        ['Sự cố','exclamation-triangle','admin.incidents.index',[]],
        ['Đánh giá','star','admin.content.index',['kind'=>'reviews']],
        ['Thông báo','bell','admin.announcements.index',[]],
        ['Chatbot AI','robot','admin.chatbot-analytics',[]],
        ['Báo cáo','bar-chart','admin.reports.index',[]],
        ['Cài đặt','gear','admin.settings.edit',[]],
    ];
@endphp
<nav class="admin-nav" aria-label="Menu chính">
@foreach($navigation as [$label,$icon,$destination,$parameters])
@php
    $active = request()->routeIs(str_ends_with($destination, '.index') ? substr($destination,0,-5).'*' : $destination);
    if ($destination === 'admin.content.index') $active = request()->route('kind') === $parameters['kind'];
    if ($destination === 'admin.bookings.index') $active = $active && request()->boolean('fixed') === isset($parameters['fixed']);
@endphp
<a href="{{ route($destination,$parameters) }}" @class(['active'=>$active]) @if($active) aria-current="page" @endif><i class="bi bi-{{ $icon }}" aria-hidden="true"></i>{{ $label }}</a>
@endforeach</nav>
<details class="admin-extra"><summary>Danh mục & công cụ khác</summary><nav class="admin-nav">
@foreach(['admin.court-types.index'=>'Loại sân','admin.maintenance.index'=>'Bảo trì','admin.knowledge-base.index'=>'Kiến thức AI','admin.users.index'=>'Người dùng','admin.roles.index'=>'Vai trò & quyền','contacts.index'=>'Liên hệ & hỗ trợ','incident-tickets.index'=>'Khiếu nại khách hàng','admin.reports.cash-flow'=>'Báo cáo dòng tiền'] as $destination=>$label)<a href="{{ route($destination) }}">{{ $label }}</a>@endforeach
@foreach(\App\Http\Controllers\AdminContentController::TYPES as $kind=>$definition)@if(!in_array($kind,['services','reviews']))<a href="{{ route('admin.content.index',$kind) }}">{{ $definition[1] }}</a>@endif @endforeach
</nav></details>
