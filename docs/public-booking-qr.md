# QR booking trên điện thoại ngoài Wi-Fi

QR được sinh tự động khi mở chi tiết booking/trang QR, không cần nhân viên tạo thủ công. Nội dung là URL ký số theo booking ID; cùng booking giữ nguyên URL khi trạng thái thay đổi, miễn tên miền và APP_KEY không đổi. Không cần lưu ảnh QR vào database.

QRCodeService dùng UrlGenerator độc lập, tránh route generator đã khởi tạo theo localhost làm sai QR_BASE_URL. Không ép domain của các route khác theo QR.

## Triển khai

1. Chạy SmashZone trên hosting HTTPS hoặc tunnel đang hoạt động tới web server của máy này.
2. Đặt QR_BASE_URL thành URL HTTPS công khai của chính ứng dụng, không phải localhost/IP LAN. APP_URL nên trỏ domain công khai khi triển khai chính thức.
3. Chạy php artisan config:clear sau khi đổi cấu hình và tải lại trang QR.
4. Điện thoại tắt Wi-Fi, dùng dữ liệu di động để quét. Máy chủ/tunnel phải tiếp tục hoạt động.

Tunnel tạm thời có thể đổi domain khi khởi động lại; QR đã in theo domain cũ sẽ không còn truy cập được. Domain cố định là lựa chọn cho vận hành thực tế.

Quét QR chỉ đọc thông tin booking. Không tự check-in qua GET; nhân viên đăng nhập và thao tác nhận sân theo quyền, trạng thái thanh toán và giờ chơi hiện có. Người có QR có thể xem thông tin đơn trên trang scan; không chia sẻ QR công khai.

## Kiểm tra lượt sửa

- PublicBookingQrTest và test_each_booking_automatically_has_a_qr: 2 tests / 32 assertions đạt.
- Bao gồm public URL, SVG, tính ổn định, không ép domain các route khác, chữ ký bị sửa trả 403, trạng thái mới nhất và quét không làm check-in.
- QR_BASE_URL hiện tại là tunnel cũ không phân giải được; không có listener cổng 8000 tại lúc kiểm tra. Chưa xác nhận được truy cập Internet/4G thực tế. Cần domain/tunnel hoạt động để hoàn tất bước triển khai.
