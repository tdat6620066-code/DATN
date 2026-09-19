<link rel="stylesheet" href="{{ asset('css/customer-footer.css') }}?v={{ filemtime(public_path('css/customer-footer.css')) }}">
<footer class="sc-footer" id="contact">
    <div class="sc-container">
        <div class="sc-footer-grid">
            <div>
                <a class="sc-footer-brand" href="{{ route('home') }}">
                    <img src="{{ asset('images/logo.png') }}" alt="SmashZone logo">
                </a>
                <p class="sc-footer-motto">BOOK · PLAY · CONNECT</p>
                <p>Từ lịch hẹn đến trận đấu. Tìm sân, kết nối và tận hưởng từng khoảnh khắc trên sân cùng SmashZone.</p>
            </div>
            <div>
                <h4>Khám phá</h4>
                <a href="{{ route('home') }}">Trang chủ</a>
                <a href="{{ route('courts.index') }}">Sân cầu lông</a>
                <a href="{{ route('home') }}#offers">Khuyến mãi</a>
                <a href="{{ route('home') }}#services">Dịch vụ tại sân</a>
                <a href="{{ route('home') }}#news">Tin tức</a>
            </div>
            <div>
                <h4>Hỗ trợ</h4>
                <a href="{{ route('contacts.index') }}">Liên hệ hỗ trợ</a>
                <a href="{{ route('information') }}">Thông tin & câu hỏi thường gặp</a>
                <a href="{{ route('bookings.index') }}">Lịch đặt của tôi</a>
                <a href="{{ route('home') }}#how-it-works">Hướng dẫn đặt sân</a>
            </div>
            <div>
                <h4>Liên hệ</h4>
                <p>Cần hỗ trợ trước hoặc sau khi đặt sân? Gửi yêu cầu để trao đổi với SmashZone.</p>
                <a href="{{ route('contacts.index') }}">Kết nối với SmashZone <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
            </div>
        </div>
        <div class="sc-footer-bottom">© {{ now()->year }} SmashZone. All rights reserved.</div>
    </div>
</footer>
