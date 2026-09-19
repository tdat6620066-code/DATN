@auth
@if((auth()->user()->role ?: 'CUSTOMER') === 'CUSTOMER')
@once
<button type="button" class="ai-chat-launcher" id="ai-chat-launcher" aria-label="Mở SmashZone Assistant" aria-controls="ai-chat-panel" aria-expanded="false"><i class="bi bi-chat-dots-fill" aria-hidden="true"></i></button>
<section class="ai-chat-panel" id="ai-chat-panel" role="dialog" aria-label="SmashZone Assistant" hidden data-endpoint="{{ route('api.ai.chat.stream') }}" data-csrf="{{ csrf_token() }}" data-login="{{ route('login') }}" data-booking="{{ route('bookings.create') }}" data-notifications="{{ route('notifications.index') }}" data-settings="{{ route('notification-settings.edit') }}">
<header class="ai-chat-header"><i class="bi bi-robot fs-3" aria-hidden="true"></i><div><strong>SmashZone Assistant</strong><small data-ai-connection>● Online</small></div><button type="button" class="ai-chat-close" aria-label="Đóng trợ lý"><i class="bi bi-x-lg" aria-hidden="true"></i></button></header>
<div class="ai-chat-messages" role="log" aria-label="Cuộc trò chuyện" aria-live="polite" aria-relevant="additions" tabindex="0"><div class="ai-chat-message bot"><div class="ai-chat-bubble">Xin chào {{ auth()->user()->name }}! Mình có thể hỗ trợ tìm sân, xem giá và khuyến mãi.</div></div></div>
<nav class="ai-chat-quick" aria-label="Thao tác nhanh"><a href="{{ route('courts.index') }}">Tìm sân trống</a><button type="button" data-chat-question="Giá thuê sân hôm nay bao nhiêu?">Giá sân hôm nay</button><a href="{{ route('home') }}#offers">Khuyến mãi</a><a href="{{ route('bookings.index') }}">Booking của tôi</a><button type="button" data-chat-history="{{ route('bookings.index') }}">Đặt như lần trước</button></nav>
<div class="ai-chat-status" role="status" aria-live="polite"></div>
<form class="ai-chat-form" method="POST" action="{{ route('api.ai.chat.stream') }}">@csrf<label class="visually-hidden" for="ai-chat-input">Câu hỏi của bạn</label><input id="ai-chat-input" name="message" maxlength="500" required placeholder="Nhập câu hỏi…" autocomplete="off"><button type="submit" aria-label="Gửi câu hỏi"><i class="bi bi-send-fill" aria-hidden="true"></i></button></form>
</section>
@endonce
@endif
@endauth
