# Rà soát luồng hoàn tiền

## Luồng thực tế

1. Khách gửi sự cố, mong muốn và tài khoản nhận tiền nếu yêu cầu hoàn.
2. Nhân viên xác minh/đề xuất; Admin chấp thuận số tiền.
3. Nếu khách đã chọn hoàn và có thông tin nhận tiền, IncidentTicketService tạo khoản hoàn APPROVED ngay: không cần khách chọn lại hoặc Admin duyệt lần hai.
4. Các phương án khác đi qua IncidentResolutionController: khách chọn hoàn/đổi lịch/đổi sân; khoản chênh lệch cần hoàn có thể tạo yêu cầu PENDING riêng.
5. Nhân sự có refunds.process chọn chuyển khoản hoặc tiền mặt, bắt đầu chi trả để khóa thông tin nhận tiền.
6. Thực hiện chi trả bên ngoài hệ thống, nhập mã giao dịch và tải ảnh biên nhận. Chỉ lúc này ghi COMPLETED, cập nhật tiền hoàn và đóng sự cố theo điều kiện.

Nhánh hoàn đặc biệt được tạo từ booking bởi nhân viên/Admin. Admin có thể tạo và duyệt ngay; không dùng nhánh này khi booking đã có phương án sự cố.

## Đã giảm rối ở giao diện

- Ẩn form xác nhận tài khoản lặp khi xác nhận hiện tại còn hiệu lực; vẫn cho sửa và xác nhận lại khi hết hạn theo cấu hình 24 giờ.
- Có nút đi thẳng từ sự cố tới khoản chi trả cho người có quyền.
- Thể hiện ba giai đoạn Gửi yêu cầu → Duyệt → Chi trả, phân biệt duyệt với nhận tiền.
- Hướng dẫn rõ tài khoản ngân hàng chỉ cần cho chuyển khoản, không cần với tiền mặt.
- Sửa link quay lại của khách: về booking thay vì danh sách dành cho nhân viên bị 403.

## Còn phức tạp về nghiệp vụ

- Có trạng thái ticket, phương án xử lý, yêu cầu hoàn và giao dịch hoàn riêng. Không nên xóa các bản ghi này để đơn giản hóa UI vì chúng phục vụ các nhiệm vụ khác nhau.
- Nhãn payout_label có thể ghi chờ tài khoản trước khi nhân viên chọn phương thức, dù tiền mặt không cần tài khoản; cần chốt cách chọn phương thức sớm hơn nếu thay đổi toàn luồng.
- Xác nhận ngân hàng có hạn 24 giờ; có thể gây chờ khi duyệt muộn. Chưa thay đổi quy tắc này.
- Nhánh khách chọn phương án có thể cần Admin duyệt tiếp khoản hoàn. Chưa tự động bỏ bước duyệt tài chính này.

Không đổi database, route, công thức hoàn, quyền duyệt, kiểm tra hạn mức, khóa đồng thời hay yêu cầu biên nhận. Đây là đơn giản hóa điều hướng và cách trình bày, không hợp nhất nghiệp vụ hoàn tiền.
