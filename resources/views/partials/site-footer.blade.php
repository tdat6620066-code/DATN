<style>
    .sc-footer { background: #082536; color: #d5e2e5; padding: 56px 0 24px; margin-top: 60px; }
    .sc-container { width: min(1180px, calc(100% - 32px)); margin-inline: auto; }
    .sc-footer-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 36px; }
    .sc-footer-brand { color: #fff; font-size: 20px; font-weight: 800; text-decoration: none; }
    .sc-footer-brand img { height: 40px; border-radius: 8px; margin-right: 8px; vertical-align: middle; }
    .sc-footer-grid p, .sc-footer-grid a { display: block; color: #a8bdc4; font-size: 13px; line-height: 1.7; margin: 12px 0; text-decoration: none; }
    .sc-footer-grid h4 { color: #fff; font-size: 13px; font-weight: 800; margin: 4px 0 14px; }
    .sc-footer-bottom { margin-top: 34px; padding-top: 20px; border-top: 1px solid rgba(255, 255, 255, .12); color: #8ca4ae; font-size: 12px; }
    @media (max-width: 640px) { .sc-footer-grid { grid-template-columns: 1fr 1fr; gap: 26px; } }
</style>
<footer class="sc-footer">
    <div class="sc-container">
        <div class="sc-footer-grid">
            <div>
                <a class="sc-footer-brand" href="{{ route('home') }}">
                    <img src="{{ asset('images/logo.png') }}" alt="SmashZone logo">
                </a>
                <p>Nền tảng đặt sân cầu lông đơn giản, nhanh chóng và tiện lợi.</p>
            </div>
            <div>
                <h4>Khám phá</h4>
                <a href="{{ route('home') }}">Trang chủ</a>
                <a href="{{ route('courts.index') }}">Sân cầu lông</a>
                <a href="{{ route('home') }}#offers">Khuyến mãi</a>
                <a href="{{ route('home') }}#news">Tin tức</a>
            </div>
            <div>
                <h4>Hỗ trợ</h4>
                <a href="#">Liên hệ</a>
                <a href="#">Câu hỏi thường gặp</a>
                <a href="#">Điều khoản</a>
                <a href="#">Chính sách</a>
            </div>
            <div>
                <h4>Liên hệ</h4>
                <p>Hotline: 0982 949 974<br>Email: hello@smashzone.vn<br>Hà Nội, Việt Nam</p>
            </div>
        </div>
        <div class="sc-footer-bottom">© {{ now()->year }} SmashZone. All rights reserved.</div>
    </div>
</footer>