@extends(auth()->user()->role === 'ADMIN' ? 'layouts.admin' : (auth()->user()->role === 'EMPLOYEE' ? 'layouts.employee' : 'layouts.app'))
@section('title', 'Thông báo — SmashZone')
@section('content')
<x-customer-shell active="notifications">


    <div class="d-flex justify-content-between flex-wrap gap-2 align-items-center flex-wrap gap-3 mb-4">

        <h2 class="fw-bold mb-0">
            Thông báo
        </h2>

        <form
            method="POST"
            action="{{ route('notifications.read-all') }}"
        >

            @csrf

            @method('PATCH')

            <button
                type="submit"
                class="btn btn-outline-primary"
            >
                Đánh dấu tất cả đã đọc
            </button>

        </form>

    </div>

    @if (session('success'))

        <div class="alert alert-success">
            {{ session('success') }}
        </div>

    @endif

    @php($lastNoticeDay = null)
    <div class="customer-notice-timeline">
    <div id="live-notification-page">
    @forelse ($notifications as $notification)
        @php($noticeDay = $notification->created_at->toDateString())
        @if($noticeDay !== $lastNoticeDay)
            <h3 class="customer-notice-day">{{ $notification->created_at->isToday() ? 'Hôm nay' : ($notification->created_at->isYesterday() ? 'Hôm qua' : $notification->created_at->format('d/m/Y')) }}</h3>
            @php($lastNoticeDay = $noticeDay)
        @endif

        <div
            class="card mb-3
            {{ !$notification->is_read ? 'border-primary' : '' }}"
        >

            <div class="card-body">

                <div class="d-flex justify-content-between flex-wrap gap-2">

                    <h5 class="fw-bold">

                        {{ $notification->title }}

                        @if (!$notification->is_read)

                            <span class="badge bg-primary">
                                Mới
                            </span>

                        @endif

                    </h5>

                    <small class="text-muted">
                        {{ $notification->created_at->diffForHumans() }}
                    </small>

                </div>

                <p class="mb-2">
                    {{ $notification->content }}
                </p>

                @if($notification->action_url)
                    <a href="{{ route('notifications.open', $notification) }}" class="btn btn-sm btn-primary me-2">
                        Xem chi tiết
                    </a>
                @endif

                @if (!$notification->is_read)

                    <form
                        method="POST"
                        action="{{ route('notifications.read', $notification) }}"
                    >

                        @csrf

                        @method('PATCH')

                        <button
                            type="submit"
                            class="btn btn-sm btn-outline-secondary"
                        >
                            Đánh dấu đã đọc
                        </button>

                    </form>

                @endif

            </div>

        </div>

    @empty

        <div class="alert alert-info">
            Bạn chưa có thông báo nào.
        </div>

    @endforelse

    {{ $notifications->links() }}
    </div>


</div></x-customer-shell>
@endsection
