# Homepage — Premium Sport / Editorial

Phạm vi chỉ Homepage. Giữ route, DB và nghiệp vụ booking/payment. HomeController thêm dữ liệu đọc cho lịch hôm nay (tối đa hai sân × bốn khung giờ tương lai) và dịch vụ đang hoạt động; không thay biến Blade cũ.

## File tạo

- resources/css/home-editorial.css
- resources/views/partials/home-live-courts.blade.php
- public/images/court-editorial.svg
- docs/homepage-editorial.md

## File sửa

- resources/views/home.blade.php
- resources/css/home.css
- resources/js/home.js
- app/Http/Controllers/HomeController.php

## Thành phần

Hero tối với minh họa sân SVG, chữ lớn, panel tìm kiếm nổi, discovery lớn/nhỏ, lịch sân backend, benefits bento, hướng dẫn bốn bước theo trục dọc, mô phỏng màn hình tài khoản bằng link thật, campaign khuyến mãi, dịch vụ cuộn ngang, phần cộng đồng, review cuộn với nút điều hướng, tin tức lớn/nhỏ và CTA cuối trang.

## Phát hiện và xử lý

- Banner cũ có số liệu đóng trong ảnh: bỏ khỏi Homepage để không trình bày số liệu không kiểm chứng.
- Ảnh sân hiện đang dùng logo: không dùng làm ảnh hero. Tạo minh họa SVG có alt rõ ràng. Giữ ảnh sân từ DB, không giả ảnh sân thực tế.
- Chưa có ảnh player cutout/community và ảnh từng dịch vụ: dùng minh họa/typography thay thế; không tuyên bố đã hoàn thành phiên bản photographic.
- Không có đánh giá APPROVED thì hiển thị empty state, không tạo review giả.
- Lịch trống lấy CourtAvailabilityService và BookingService; loại slot đã bắt đầu, slot không có giá không hiện có thể đặt. Lịch là snapshot khi tải trang, không tuyên bố realtime push.
- Services sử dụng ServiceItem thay vì trình bày amenities như sản phẩm có giá.
- Số lượng sân, lượt đặt và đánh giá vẫn dùng dữ liệu backend; không lấy ví dụ 50+/100K+ làm dữ liệu thật.

## Kiểm tra

Build Vite, view:cache và static route/CSRF audit đạt. CommunicationUiTest, CourtUiTest, CustomerDashboardTest: 18 tests / 169 assertions đạt.
Edge headless kiểm tra homepage 375/768/1440/1920: không overflow, không Runtime.exceptionThrown, menu mobile mở được, không ảnh tải lỗi trong DOM đã tải. Kết quả tại storage/app/editorial-browser-results.json.
Đây chưa phải audit WCAG đầy đủ. Không kiểm thử giao dịch thanh toán thật. Dừng sau Homepage để duyệt thiết kế.
