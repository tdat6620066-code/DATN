<nav class="staff-nav" aria-label="Điều hướng nhân viên">
    @if(auth()->user()->hasPermission('employee.dashboard'))
        <a class="{{ request()->routeIs('employee.dashboard') && !request('panel') ? 'active' : '' }}" href="{{ route('employee.dashboard') }}"><i class="bi bi-grid" aria-hidden="true"></i>Tổng quan</a>
        <a class="{{ request()->routeIs('employee.schedule') ? 'active' : '' }}" href="{{ route('employee.schedule') }}"><i class="bi bi-calendar-week" aria-hidden="true"></i>Lịch sân</a>
    @endif
    @if(auth()->user()->hasPermission('bookings.view'))
        <a class="{{ request()->routeIs('employee.bookings.*') ? 'active' : '' }}" href="{{ route('employee.bookings.index') }}"><i class="bi bi-journal-check" aria-hidden="true"></i>Booking</a>
        <a href="{{ route('employee.bookings.index', ['status'=>'CONFIRMED','date'=>today()->toDateString()]) }}#staff-checkin"><i class="bi bi-qr-code-scan" aria-hidden="true"></i>Check-in</a>
    @endif
    @if(auth()->user()->hasPermission('services.manage'))<a href="{{ auth()->user()->hasPermission('payments.counter') ? route('employee.retail.index') : route('employee.equipment.index') }}"><i class="bi bi-bag" aria-hidden="true"></i>Dịch vụ</a>@endif
    @if(auth()->user()->hasPermission('incidents.manage'))<a class="{{ request()->routeIs('employee.incidents.*') ? 'active' : '' }}" href="{{ route('employee.incidents.index') }}"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i>Sự cố</a>@endif
    @if(auth()->user()->hasPermission('employee.dashboard') && auth()->user()->hasPermission('bookings.view'))<a class="{{ request('panel') === 'customers' ? 'active' : '' }}" href="{{ route('employee.dashboard', ['panel'=>'customers']) }}"><i class="bi bi-people" aria-hidden="true"></i>Khách hàng</a>@endif
    <a href="{{ route('notifications.index') }}"><i class="bi bi-bell" aria-hidden="true"></i>Thông báo</a>
</nav>
<div class="staff-section-label">CÔNG CỤ VẬN HÀNH</div>
<nav class="staff-nav">
    @if(auth()->user()->hasPermission('bookings.view') && auth()->user()->hasPermission('payments.counter'))<a href="{{ route('employee.counter.create') }}"><i class="bi bi-plus-circle" aria-hidden="true"></i>Đặt tại quầy</a>@endif
    @if(auth()->user()->hasPermission('courts.status.manage'))<a href="{{ route('employee.courts.index') }}"><i class="bi bi-columns-gap" aria-hidden="true"></i>Quản lý sân</a>@endif
    @if(auth()->user()->hasPermission('services.manage'))<a href="{{ route('employee.equipment.index') }}"><i class="bi bi-box-seam" aria-hidden="true"></i>Thiết bị</a>@endif
    @if(auth()->user()->hasPermission('incidents.manage'))<a href="{{ route('incident-tickets.index') }}"><i class="bi bi-headset" aria-hidden="true"></i>Khiếu nại khách hàng</a>@endif
    @if(auth()->user()->hasPermission('employee.dashboard'))<a href="{{ route('employee.shifts.index') }}"><i class="bi bi-clock-history" aria-hidden="true"></i>Ca làm việc</a>@endif
</nav>
