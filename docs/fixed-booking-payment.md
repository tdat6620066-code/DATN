# Thanh toán lịch cố định

Lịch mới: chọn các buổi → giải quyết trùng lịch → xác nhận tổng → giữ chỗ 15 phút → thanh toán VNPay một lần → xác nhận tất cả buổi.

- `payments.fixed_booking_id` là duy nhất, `booking_id` để trống với giao dịch lịch cố định. Không tạo giao dịch riêng cho mỗi buổi.
- `fixed_bookings.total_price` lưu tổng đã chốt. `bookings.total_amount` là giá cuối của từng buổi sau phân bổ ưu đãi, dùng làm giới hạn hoàn tiền (tương đương `final_price`).
- Trạng thái nhóm: `AWAITING_PAYMENT`, `PAYMENT_FAILED`, `ACTIVE`, `COMPLETED`, `EXPIRED`. Bản xem trước nằm trong session, chưa tạo booking. Lịch có trước migration mang trạng thái `LEGACY` và giữ thanh toán riêng.
- Return/IPN kiểm tra chữ ký, merchant và số tiền. Xác nhận toàn nhóm trong transaction; callback lặp không xác nhận lại buổi đã hủy/hoàn tiền. Callback thành công quá hạn ghi `VNPAY_REQUIRES_REVIEW` trong lịch sử giao dịch để nhân viên đối chiếu, không chiếm lại sân.
- `bookings:expire-holds` đã được lên lịch mỗi phút trong `routes/console.php`. Khi triển khai cần chạy Laravel scheduler (`php artisan schedule:work` khi phát triển). Kiểm tra chỗ trống và callback vẫn từ chối hold hết hạn ngay cả khi scheduler chưa chạy.
- Hoàn tiền tiếp tục qua quy trình sự cố, duyệt và chi trả thủ công hiện có. Một refund gắn với yêu cầu của một booking con và giao dịch chung. Báo cáo ghi một khoản thu, trừ từng khoản hoàn trên ngày chi trả; lọc theo booking chỉ phân bổ giá trị buổi tương ứng.
- Không cộng dịch vụ phát sinh vào giao dịch lịch cố định đã chốt. Hiện chưa có luồng thu riêng cho các dịch vụ đó.
- Lịch giảm giá về 0 được xác nhận miễn phí, không gửi giao dịch 0đ sang VNPay. Chỉ VNPay đang được tích hợp; MoMo/ZaloPay chưa được bổ sung.

Migration local đã chạy: `2026_09_10_010000_add_fixed_booking_payments`.

Kiểm thử liên quan (SQLite trong bộ nhớ, không dùng database local):

```powershell
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --filter 'RecurringBookingTest|RevenueReportTest|SpecialRefundTest|IncidentResolutionTest|IncidentTicketTest|BulkIncidentTest|AdminPaymentTest|PaymentSecurityTest|ManualRefundPayoutTest|OperationsReportTest|CheckoutBookingTest'
```

VNPay được kiểm tra bằng callback ký trong test và URL tạo ra; chưa chạy giao dịch thật qua gateway. Bộ kiểm thử toàn dự án chưa xanh: lần chạy toàn bộ có 10 errors và 13 failures ngoài nhóm kiểm thử liên quan (gồm route AI thiếu, dữ liệu đăng nhập/nhân viên và các kỳ vọng cũ về hủy booking).
