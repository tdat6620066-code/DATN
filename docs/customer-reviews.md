# Bình luận và đánh giá Customer

Khách mở Booking của tôi → chi tiết booking đã hoàn thành → Đánh giá trải nghiệm. Chọn 1–5 sao và nhập bình luận tối đa 2.000 ký tự.

- Chỉ chủ booking với vai trò CUSTOMER được gửi.
- Chỉ đánh giá sân thuộc chi tiết không bị hủy của booking COMPLETED.
- Mỗi cặp booking/sân có tối đa một đánh giá. Khóa bản ghi booking trong transaction giúp ngăn gửi trùng đồng thời qua endpoint này.
- user_id lấy từ phiên đăng nhập, status luôn PENDING; client không thể tự duyệt.
- Admin dùng màn hình Bình luận hiện có để duyệt/ẩn. Trang sân và điểm tổng hợp chỉ dùng đánh giá APPROVED.
- Khách xem trạng thái ở chi tiết booking hoặc Hồ sơ → Đánh giá. Nội dung được Blade escape.
- Không đổi schema. Thêm POST /booking/{booking}/reviews, tên bookings.reviews.store, trong middleware Customer hiện có, giới hạn 10 request/phút. Không đổi route cũ.
- Phạm vi là đánh giá trải nghiệm sân theo booking, chưa có luồng đánh giá từng dịch vụ hoặc trả lời bình luận.

File tạo: CustomerReviewController.php, partials/customer-booking-reviews.blade.php, CustomerReviewTest.php và tài liệu này.
File sửa: routes/web.php, bookings/show.blade.php, profile/index.blade.php.

Kiểm tra: CustomerReviewTest + CustomerDashboardTest + CourtUiTest đạt 15 tests / 148 assertions. Bao gồm quyền sở hữu, trạng thái, sân không thuộc booking, validation, gửi trùng, không cho client tự duyệt và ẩn đánh giá chờ duyệt khỏi trang công khai.

Tài liệu này cập nhật mục đánh giá còn thiếu trong customer-usecase-audit.md.
