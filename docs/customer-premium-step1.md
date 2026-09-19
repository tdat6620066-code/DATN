# Customer Premium — Step 1

Phạm vi: design system và layout dùng chung. Chưa thực hiện Step 2–10.

Tái sử dụng layouts.app, site-header/footer, Bootstrap collapse/dropdown, notification-dropdown, design system smashzone.css và các component hiện có. Controller, route, DB, CSRF và các luồng nghiệp vụ không đổi.

## Thay đổi

- Navbar có wordmark SmashZone, BOOK · PLAY · CONNECT, tìm sân, lịch sân, menu active, nút tìm kiếm, thông báo và tài khoản.
- Menu tài khoản hiển thị tên thật; avatar chữ cái đầu. Giữ nguyên logout POST/CSRF.
- Navigation thu gọn dưới 1200px để không ép menu ở tablet. Escape đóng menu và trả focus về nút mở.
- Trạng thái scroll có shadow; footer bổ sung nhận diện và link dịch vụ thật.
- Typography, radius, button, input, panel, focus và reduced-motion áp dụng riêng layout Customer.
- Không tạo link chính sách hoặc social khi chưa có đích thật.

## File

Tạo public/css/customer-premium.css, public/js/customer-layout.js và tài liệu này.
Sửa resources/views/layouts/app.blade.php, resources/views/partials/site-header.blade.php, resources/views/partials/site-footer.blade.php.

## Kiểm tra

- 30 tests / 246 assertions đạt: CommunicationUiTest, CustomerDashboardTest, CourtUiTest, CustomerReviewTest, DailyBookingDurationTest, BookingOperationsFlowTest.
- view:cache và node --check đạt. Static audit không phát hiện route thiếu, placeholder link, POST thiếu CSRF cục bộ hoặc duplicate extends.
- Edge headless: homepage và danh sách sân ở 375, 430, 768, 1024, 1366, 1440, 1920px không tràn ngang; menu mobile mở được; không ghi nhận Runtime.exceptionThrown.
- Kết quả: storage/app/step1-browser-results.json. Ảnh kiểm tra ở storage/app/step1-home-375.png, step1-home-1440.png và tương tự cho courts.
- Browser kiểm tra Guest; quyền Customer/Staff/Admin và notification được kiểm tra bằng tests. Chưa kiểm tra mọi trang đăng nhập bằng browser trong Step 1.
- Banner homepage cũ có số liệu nằm trong ảnh, chưa đối chiếu được với DB. Cần xử lý ở Step 2, không dùng các con số này làm dữ liệu thống kê.

Không tuyên bố đã hoàn thành redesign homepage, gallery, slot picker hay checkout; các phần đó thuộc bước tiếp theo.
