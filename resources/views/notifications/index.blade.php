<!DOCTYPE html>
<html lang="vi">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Thông báo - SmashZone</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

</head>

<body>

<div class="container py-5">

    <div class="d-flex justify-content-between align-items-center mb-4">

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

    @forelse ($notifications as $notification)

        <div
            class="card mb-3
            {{ !$notification->is_read ? 'border-primary' : '' }}"
        >

            <div class="card-body">

                <div class="d-flex justify-content-between">

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

</body>

</html>