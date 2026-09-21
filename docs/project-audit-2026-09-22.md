# Rà soát project 22/09/2026

## Phạm vi và kết quả

- Chạy PHPUnit toàn bộ với SQLite `:memory:`; không reset/seed MySQL thật.
- Kiểm tra cú pháp 289 file PHP trong app/routes/config/database. Phát hiện một file lỗi: ChatController.php; đã sửa và kiểm tra lại file thành công.
- Blade cache và Vite production build thành công.
- Static UI audit: không phát hiện route literal thiếu, link placeholder, form POST thiếu CSRF tại chỗ hay duplicate extends. Vẫn có 34 file inline CSS và 15 file inline JS, là nợ kỹ thuật chứ không tự động đồng nghĩa lỗi.
- Route list xuất thành công sau sửa controller; không có migration pending.
- HTTP thực tế: `/`, `/courts`, `/tin-tuc`, `/login`, `/register` đều 200.
- Đối chiếu 50 booking: không phát hiện stale hold, booking hoạt động thiếu receipt PAID, payment PAID thiếu paid_at hoặc booking đóng còn detail hoạt động theo script audit hiện tại. Đây không phải đối soát với ngân hàng.

## Lỗi đã sửa

ChatController có đoạn logic trả lời cũ bị ghép vào hàm payload và đoạn PHP nằm ngoài method, gây parse error. Khôi phục validation message/action/choice_id và giữ pipeline AiChatbotService, JSON/NDJSON hiện tại. Giới hạn message 500 ký tự theo test hiện có.

## Test và phần còn lại

- Lần đầu: 311 tests, 2099 assertions, 35 failures, 1 error.
- Sau sửa cú pháp: 311 tests, 2142 assertions, 24 failures, 1 error.
- Sau khôi phục giới hạn message, chạy lại CommunicationUiTest + ChatbotChatTest + AiApiTest: 19 tests, 112 assertions, tất cả đạt. Chưa chạy lại toàn bộ sau chỉnh giới hạn này; kết quả full suite bên trên còn chứa một failure message length đã được xử lý.
- Chi tiết máy đọc: `storage/app/project-audit-final.xml`; log `storage/app/project-audit-final.log`.

Nhóm cần xử lý tiếp:

1. BookingTest login: fixture tạo cột `users.login` không tồn tại; đăng ký kỳ vọng email_verified_at có ngay. Cần sửa fixture và xác nhận chính sách xác thực, không thêm cột chỉ để test qua.
2. AuthorizationMatrix/ExampleTest kỳ vọng homepage chuyển về login hoặc dashboard trong khi homepage hiện công khai. ExampleTest không dùng RefreshDatabase nên có thể gặp lỗi database test chưa tạo.
3. CourtStatus/EmployeeDashboard và một số EmployeeOperations: trả 403 hoặc chuyển hướng không như kỳ vọng; kiểm tra permissions fixture và input đăng nhập.
4. EmployeeOperations: nhiều lỗi phát sinh từ helper check-in lúc 10h cho lịch 18h; cần cập nhật thời điểm test theo quy định check-in hiện tại. Test bán lẻ/báo cáo còn lệch tổng tiền, chưa kết luận nguyên nhân.
5. CheckoutBookingTest nhận 422 thay vì 200; cần kiểm tra đầy đủ điều kiện tiền sân/dịch vụ/đồ thuê của fixture.
6. AdminManagementTest bài vừa publish nhận 404; cần kiểm tra mốc published_at và điều kiện hiển thị.
7. RecurringBookingTest lệch số lượng 2 so với 1; cần truy nguyên trước khi sửa logic nhóm lịch.

## Giới hạn

Chưa kiểm tra tay toàn bộ màn hình đăng nhập theo từng vai trò, toàn bộ viewport, giao dịch VNPay thật, QR ngoài mạng hoặc độ an toàn dependency. Không khẳng định project đã hết lỗi. Không sửa nghiệp vụ hay vô hiệu hóa test để che các failure.
