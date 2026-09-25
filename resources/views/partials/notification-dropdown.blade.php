@auth
@php
    $navNotifications = auth()->user()->userNotifications()->latest()->orderByDesc('id')->limit(6)->get();
    $navUnread = auth()->user()->userNotifications()->where('is_read', false)->count();
@endphp
<div class="dropdown sz-notifications" data-live-url="{{ route('notifications.live') }}" data-notifications data-user="{{ auth()->id() }}" data-all-url="{{ route('notifications.index') }}">
<button class="sz-notification-trigger" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="Thông báo, {{ $navUnread }} chưa đọc"><i class="bi bi-bell" aria-hidden="true"></i><span class="sz-notification-count" data-unread-count="{{ $navUnread }}" @if(!$navUnread) hidden @endif>{{ $navUnread>99?'99+':$navUnread }}</span></button>
<div class="dropdown-menu dropdown-menu-end sz-notification-menu">
<div class="d-flex justify-content-between align-items-center gap-2 p-3 border-bottom"><strong>Thông báo</strong><a href="{{ route('notifications.index') }}">Xem tất cả</a></div>
<div class="sz-notification-list" data-notification-list>
@forelse($navNotifications as $notice)<article class="sz-notification-item {{ !$notice->is_read?'is-unread':'' }}"><i class="bi bi-{{ str_contains($notice->type ?? '', 'PAYMENT') ? 'credit-card' : (str_contains($notice->type ?? '', 'BOOKING') ? 'calendar-check' : 'bell') }}" aria-hidden="true"></i><div><a class="sz-notification-title" href="{{ route('notifications.open',$notice) }}">{{ $notice->title }}</a><p>{{ Str::limit($notice->content,160) }}</p><time datetime="{{ $notice->created_at->toIso8601String() }}" title="{{ $notice->created_at->format('d/m/Y H:i') }}">{{ $notice->created_at->diffForHumans() }}</time>@if(!$notice->is_read)<span class="sz-unread-label">● Chưa đọc</span><form method="POST" action="{{ route('notifications.read',$notice) }}">@csrf @method('PATCH')<button class="sz-read-button" type="submit">Đánh dấu đã đọc</button></form>@endif</div></article>
@empty<p class="text-muted text-center p-4 mb-0" data-notification-empty>Chưa có thông báo.</p>@endforelse
</div>
<form class="p-3 border-top" method="POST" action="{{ route('notifications.read-all') }}">@csrf @method('PATCH')<button class="btn btn-outline-primary btn-sm w-100" data-read-all @disabled(!$navUnread)>Đánh dấu tất cả đã đọc</button></form>
</div></div>
@endauth
