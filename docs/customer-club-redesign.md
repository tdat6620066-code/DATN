# Customer Club — visual redesign

Thay composition hero bằng ảnh sân SmashZone có sẵn, không dùng banner chứa số liệu in sẵn. Headline ba dòng, overlay đọc được, chữ nền, đường sân, link nổi kiểm tra lịch và scroll indicator. Navbar home trong suốt, khi cuộn nổi trên nền kính; trang Customer khác dùng navigation nổi nền sáng.

Quick Booking có trường loại sân thật từ dữ liệu hiện có. Không tạo bộ lọc khu vực vì backend hiện chưa có trường tương ứng. Discovery chuyển thành mosaic năm sân với ảnh/tên/giá/link từ DB; không tự tạo loại sân huấn luyện/thi đấu nếu dữ liệu chưa có.

Lớp nhận diện Customer áp dụng cả phần đầu tìm sân, tài khoản, checkout và card lịch đặt. Cấu trúc form, controller, route, phân quyền và tính tiền được giữ nguyên. Đây chưa phải viết lại từng màn hình Customer; booking/detail vẫn tái sử dụng cấu trúc hiện có.

## File tạo
- public/css/customer-club.css
- public/images/club-hero.png (bản sao asset sân hiện có, không tạo ảnh mới)
- docs/customer-club-redesign.md

## File sửa
- resources/views/home.blade.php
- resources/views/partials/site-header.blade.php

## Kiểm tra
- Build Vite và view:cache đạt.
- 22 tests / 209 assertions đạt: CommunicationUiTest, CourtUiTest, CustomerDashboardTest, BookingCheckoutUiTest.
- Static audit: không route literal thiếu, không link placeholder, không POST form thiếu CSRF cục bộ.
- Browser guest Homepage ở 375/768/1440/1920: không tràn ngang, không lỗi Runtime JS, không ảnh tải lỗi; menu mobile hoạt động. Ảnh và kết quả trong storage/app/editorial-*.
- Chưa kiểm tra mọi trang Customer đăng nhập bằng browser. Không xác nhận WCAG toàn diện hoặc thanh toán thật.

## Dữ liệu còn thiếu
Khuyến mãi/tin tức không có ảnh vẫn dùng placeholder có nhãn; đánh giá chưa công bố vẫn là empty state. Cần dữ liệu nội dung thật để các phần đó đạt chất lượng hình ảnh tương đương hero và sân.
