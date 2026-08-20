@extends('layouts.employee')

@section('title', 'Lịch đặt sân - SmashZone')
@section('page_heading', 'Lịch đặt sân')

@push('styles')
<style>
    .schedule-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }
    .schedule-toolbar .mode-tabs {
        display: inline-flex;
        gap: 5px;
        padding: 4px;
        border-radius: 11px;
        background: #e9f0ed;
    }
    .schedule-toolbar .mode-tabs a {
        padding: 8px 17px;
        border-radius: 8px;
        color: #5c747d;
        font-size: 12px;
        font-weight: 800;
        text-decoration: none;
        transition: .2s;
    }
    .schedule-toolbar .mode-tabs a.active {
        background: #0b3041;
        color: #fff;
    }
    .schedule-nav {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .schedule-nav a {
        display: grid;
        place-items: center;
        width: 36px;
        height: 36px;
        border: 1px solid #dbe5e1;
        border-radius: 9px;
        color: #41606c;
        text-decoration: none;
        transition: .2s;
    }
    .schedule-nav a:hover {
        border-color: #08b96b;
        color: #00874e;
    }
    .schedule-nav .nav-label {
        min-width: 160px;
        text-align: center;
        font-size: 14px;
        font-weight: 800;
        color: #153443;
    }
    .schedule-datepicker {
        position: relative;
        display: inline-flex;
        align-items: center;
    }
    .schedule-datepicker i {
        position: absolute;
        left: 12px;
        z-index: 1;
        pointer-events: none;
        color: #08b96b;
        font-size: 15px;
    }
    .schedule-datepicker input[type="date"] {
        height: 38px;
        padding: 0 12px 0 36px;
        border: 1px solid #dbe5e1;
        border-radius: 9px;
        background: #fff;
        color: #153443;
        font-size: 12px;
        font-weight: 700;
        font-family: inherit;
        cursor: pointer;
        outline: none;
    }
    .schedule-datepicker input[type="date"]:focus {
        border-color: #08b96b;
        box-shadow: 0 0 0 3px rgba(8, 185, 107, .12);
    }
    .schedule-wrap {
        overflow-x: auto;
        border: 1px solid #dfe8e4;
        border-radius: 16px;
        background: #fff;
    }
    .schedule-table {
        min-width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }
    .schedule-table th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: #f7faf8;
        padding: 12px 14px;
        border-bottom: 1px solid #e6edea;
        text-align: center;
        white-space: nowrap;
    }
    .schedule-table th.court-col {
        position: sticky;
        left: 0;
        z-index: 3;
        text-align: left;
        min-width: 190px;
    }
    .schedule-table th .dow {
        display: block;
        color: #819198;
        font-size: 9px;
        font-weight: 800;
        letter-spacing: .5px;
        text-transform: uppercase;
    }
    .schedule-table th .day-num {
        display: block;
        font-size: 16px;
        font-weight: 800;
        color: #153443;
    }
    .schedule-table th.is-today .day-num {
        display: inline-grid;
        place-items: center;
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: #08b96b;
        color: #fff;
    }
    .schedule-table th.is-outside {
        background: #f0f4f2;
    }
    .schedule-table th.is-outside .day-num,
    .schedule-table th.is-outside .dow {
        color: #aebbbb;
    }
    .schedule-table td {
        vertical-align: top;
        padding: 8px;
        border-bottom: 1px solid #eef2f0;
        border-right: 1px solid #f1f4f2;
        min-width: 140px;
        height: 100px;
    }
    .schedule-table td.court-col {
        position: sticky;
        left: 0;
        z-index: 1;
        background: #fff;
        min-width: 190px;
    }
    .schedule-table td.court-col .court-name {
        font-size: 13px;
        font-weight: 800;
        color: #163847;
    }
    .schedule-table td.court-col .court-sub {
        color: #819198;
        font-size: 11px;
        margin-top: 3px;
    }
    .slot-chip {
        display: flex;
        align-items: flex-start;
        gap: 7px;
        padding: 7px 8px;
        margin-bottom: 5px;
        border-radius: 8px;
        border-left: 3px solid #08b96b;
        background: #eafaf2;
        color: #0b5c3a;
        font-size: 11px;
        cursor: default;
    }
    .slot-chip .time {
        font-weight: 800;
        white-space: nowrap;
    }
    .slot-chip .cust {
        line-height: 1.35;
        font-weight: 700;
    }
    .slot-chip .cust small {
        display: block;
        color: #5e7a6b;
        font-weight: 600;
    }
    .slot-chip.status-pending_payment { border-left-color: #f59e0b; background: #fff6e5; color: #9a6408; }
    .slot-chip.status-checked_in { border-left-color: #0ea5e9; background: #e8f4fc; color: #0b6390; }
    .slot-chip.status-completed { border-left-color: #64748b; background: #eef1f3; color: #475569; }
    .cell-empty {
        color: #c3cdc9;
        font-size: 11px;
        text-align: center;
        padding-top: 24px;
    }
    .schedule-legend {
        display: flex;
        gap: 18px;
        flex-wrap: wrap;
        margin-top: 14px;
        color: #6b7d84;
        font-size: 12px;
        font-weight: 700;
    }
    .schedule-legend span { display: flex; align-items: center; gap: 6px; }
    .legend-dot { width: 11px; height: 11px; border-radius: 3px; display: inline-block; }
</style>
@endpush

@section('content')
<div class="schedule-toolbar">
    <div class="mode-tabs">
        <a class="{{ $mode === 'day' ? 'active' : '' }}" href="{{ route('employee.schedule', ['mode' => 'day', 'date' => $date->toDateString()]) }}">Ngày</a>
        <a class="{{ $mode === 'week' ? 'active' : '' }}" href="{{ route('employee.schedule', ['mode' => 'week', 'date' => $date->toDateString()]) }}">Tuần</a>
        <a class="{{ $mode === 'month' ? 'active' : '' }}" href="{{ route('employee.schedule', ['mode' => 'month', 'date' => $date->toDateString()]) }}">Tháng</a>
    </div>

    <div class="schedule-datepicker">
        <i class="bi bi-calendar3"></i>
        <input type="date" id="scheduleDateInput" value="{{ $date->toDateString() }}">
    </div>

    <div class="schedule-nav">
        @if($mode === 'day')
            <a href="{{ route('employee.schedule', ['mode' => 'day', 'date' => $date->copy()->subDay()->toDateString()]) }}"><i class="bi bi-chevron-left"></i></a>
            <span class="nav-label">{{ $date->translatedFormat('l, d/m/Y') }}</span>
            <a href="{{ route('employee.schedule', ['mode' => 'day', 'date' => $date->copy()->addDay()->toDateString()]) }}"><i class="bi bi-chevron-right"></i></a>
        @elseif($mode === 'week')
            <a href="{{ route('employee.schedule', ['mode' => 'week', 'date' => $date->copy()->subWeek()->toDateString()]) }}"><i class="bi bi-chevron-left"></i></a>
            <span class="nav-label">Tuần {{ $start->format('d/m') }} - {{ $end->format('d/m/Y') }}</span>
            <a href="{{ route('employee.schedule', ['mode' => 'week', 'date' => $date->copy()->addWeek()->toDateString()]) }}"><i class="bi bi-chevron-right"></i></a>
        @else
            <a href="{{ route('employee.schedule', ['mode' => 'month', 'date' => $date->copy()->subMonth()->toDateString()]) }}"><i class="bi bi-chevron-left"></i></a>
            <span class="nav-label">Tháng {{ $date->translatedFormat('m/Y') }}</span>
            <a href="{{ route('employee.schedule', ['mode' => 'month', 'date' => $date->copy()->addMonth()->toDateString()]) }}"><i class="bi bi-chevron-right"></i></a>
        @endif
        <a href="{{ route('employee.schedule', ['mode' => $mode]) }}" title="Hôm nay"><i class="bi bi-dot"></i></a>
    </div>
</div>

<div class="schedule-wrap">
    <table class="schedule-table">
        <thead>
            <tr>
                <th class="court-col">Sân</th>
                @foreach($dates as $d)
                    <th class="{{ $d->isToday() ? 'is-today' : '' }} {{ $mode === 'month' && $d->month !== $date->month ? 'is-outside' : '' }}">
                        <span class="dow">{{ $d->translatedFormat('D') }}</span>
                        <span class="day-num">{{ $d->day }}</span>
                        <span class="dow">{{ $d->format('m/Y') }}</span>
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($courts as $court)
                <tr>
                    <td class="court-col">
                        <div class="court-name">{{ $court->name }}</div>
                        <div class="court-sub">{{ $court->courtType?->name ?? 'Sân cầu lông' }}</div>
                    </td>
                    @foreach($dates as $d)
                        <td>
                            @php
                                $cellBookings = $bookingDetails->where('court_id', $court->id)->where('booking_date', $d->toDateString());
                            @endphp
                            @if($cellBookings->isNotEmpty())
                                @foreach($cellBookings as $detail)
                                    <div class="slot-chip status-{{ strtolower($detail->booking->status) }}" title="{{ $detail->booking->user->name }} · {{ $detail->booking->booking_code }} · {{ $detail->booking->status }}">
                                        <span class="time">{{ $detail->timeSlot->name }}</span>
                                        <span class="cust">{{ $detail->booking->user->name }}<small>{{ $detail->booking->booking_code }}</small></span>
                                    </div>
                                @endforeach
                            @else
                                <div class="cell-empty">Trống</div>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ $dates->count() + 1 }}" class="cell-empty" style="padding:40px;">Hiện chưa có sân hoạt động.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="schedule-legend">
    <span><i class="legend-dot" style="background:#08b96b"></i>Đã xác nhận</span>
    <span><i class="legend-dot" style="background:#f59e0b"></i>Chờ thanh toán</span>
    <span><i class="legend-dot" style="background:#0ea5e9"></i>Đã nhận sân</span>
    <span><i class="legend-dot" style="background:#64748b"></i>Đã hoàn thành</span>
</div>
@endsection

@push('scripts')
<script>
    document.getElementById('scheduleDateInput')?.addEventListener('change', function () {
        if (this.value) {
            window.location.href = "{{ route('employee.schedule') }}?mode={{ $mode }}&date=" + this.value;
        }
    });
</script>
@endpush
