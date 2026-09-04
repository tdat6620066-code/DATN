<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cài đặt thông báo - SmashZone</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body{background:#f2f6f4;color:#102a34}.settings-shell{width:min(850px,calc(100% - 32px));margin:36px auto}.settings-card{overflow:hidden;border:1px solid #dce8e3;border-radius:18px;background:#fff;box-shadow:0 16px 45px rgba(8,38,53,.08)}.settings-head{display:flex;align-items:center;gap:12px;padding:22px 28px;border-bottom:1px solid #e8efec;font-size:20px;font-weight:850}.settings-head i{color:#0ea36b}.setting-row{display:flex;align-items:center;justify-content:space-between;gap:24px;padding:20px 28px;border-bottom:1px solid #edf2ef}.setting-copy strong{display:block;margin-bottom:4px;font-size:15px}.setting-copy span{color:#6b7d84;font-size:13px}.required-note{display:inline-block;margin-top:6px;padding:3px 8px;border-radius:999px;background:#e8f9f1;color:#08794f;font-size:10px;font-weight:800}.form-switch .form-check-input{width:48px;height:25px;cursor:pointer}.form-check-input:checked{border-color:#0ea36b;background-color:#0ea36b}.form-check-input:disabled{opacity:.65}.settings-foot{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:22px 28px}.policy{max-width:520px;color:#6b7d84;font-size:12px}.save-btn{border:0;border-radius:10px;background:#0ea36b;color:#fff;padding:11px 18px;font-weight:800}.back-link{display:inline-block;margin-bottom:14px;color:#087f57;text-decoration:none;font-weight:700}@media(max-width:600px){.setting-row,.settings-foot{align-items:flex-start}.settings-foot{flex-direction:column}.save-btn{width:100%}}
    </style>
</head>
<body>
@include('partials.site-header')
<main class="settings-shell">
    <a class="back-link" href="{{ route('profile') }}">← Tài khoản</a>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    <form method="POST" action="{{ route('notification-settings.update') }}" class="settings-card">
        @csrf @method('PUT')
        <div class="settings-head"><i class="bi bi-bell-fill"></i> CÀI ĐẶT THÔNG BÁO</div>

        @foreach([
            ['Booking','Nhận cập nhật về lịch đặt sân','bi-calendar2-check'],
            ['Thanh toán','Nhận trạng thái thanh toán','bi-credit-card'],
        ] as [$title,$description,$icon])
        <div class="setting-row"><div class="setting-copy"><strong><i class="bi {{ $icon }} me-2"></i>{{ $title }}</strong><span>{{ $description }}</span><br><small class="required-note">Bắt buộc · liên quan giao dịch</small></div><div class="form-check form-switch"><input class="form-check-input" type="checkbox" checked disabled aria-label="{{ $title }} bắt buộc"></div></div>
        @endforeach

        <div class="setting-row"><div class="setting-copy"><strong><i class="bi bi-alarm me-2"></i>Nhắc lịch</strong><span>Nhắc trước giờ chơi 1 tiếng</span></div><div class="form-check form-switch"><input type="hidden" name="reminder" value="0"><input class="form-check-input" type="checkbox" name="reminder" value="1" @checked(old('reminder',$user->notificationEnabled('reminder')))></div></div>

        <div class="setting-row"><div class="setting-copy"><strong><i class="bi bi-gift me-2"></i>Khuyến mãi</strong><span>Nhận ưu đãi từ SmashZone</span></div><div class="form-check form-switch"><input type="hidden" name="promotion" value="0"><input class="form-check-input" type="checkbox" name="promotion" value="1" @checked(old('promotion',$user->notificationEnabled('promotion')))></div></div>

        <div class="setting-row"><div class="setting-copy"><strong><i class="bi bi-envelope me-2"></i>Email</strong><span>Nhận bản sao các cập nhật booking và thanh toán qua email</span></div><div class="form-check form-switch"><input type="hidden" name="email" value="0"><input class="form-check-input" type="checkbox" name="email" value="1" @checked(old('email',$user->notificationEnabled('email')))></div></div>

        <div class="setting-row"><div class="setting-copy"><strong><i class="bi bi-shield-exclamation me-2"></i>Thông báo hệ thống</strong><span>Nhận thông báo quan trọng</span><br><small class="required-note">Bắt buộc · an toàn và vận hành</small></div><div class="form-check form-switch"><input class="form-check-input" type="checkbox" checked disabled aria-label="Thông báo hệ thống bắt buộc"></div></div>

        <div class="settings-foot"><div class="policy">Booking, thanh toán và thông báo hệ thống quan trọng không thể tắt vì ảnh hưởng trực tiếp đến lịch chơi, giao dịch hoặc an toàn tài khoản.</div><button class="save-btn" type="submit">Lưu thay đổi</button></div>
    </form>
</main>
@include('partials.site-footer')
</body></html>
