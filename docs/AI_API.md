# AI Database & API (UC24–UC28)

## Booking Copilot cấp 17

`POST /api/ai/chat` hỗ trợ yêu cầu như `Đặt cho tôi sân như tuần trước vào tối mai`.
Copilot chỉ đọc booking thuộc tài khoản đang đăng nhập, dùng sân/khung giờ gần nhất làm sở thích,
kiểm tra lịch trống và bảng giá hiện hành, tự chọn voucher hợp lệ có mức giảm cao nhất và trả về
`booking_preview`. Nếu lựa chọn cũ hết chỗ, phản hồi có tối đa ba phương án gần giờ hoặc cùng giờ ở sân khác.

Preview không tạo booking. Client phải gửi lại nút có `action=confirm_copilot_booking` và `choice_id`
được cấp trong preview. Token gắn với user/session, hết hạn sau 10 phút; khi xác nhận backend kiểm tra lại
slot, giá và voucher trong luồng transaction trước khi tạo booking `PENDING_PAYMENT`.

Các endpoint dùng session authentication hiện có và trả JSON. Hai endpoint phân tích tổng hợp yêu cầu tài khoản `ADMIN`.

| Use case | Method & endpoint | Quyền | Kết quả chính |
|---|---|---|---|
| UC24 | `GET /api/ai/courts/recommendations` | Đã đăng nhập | Sân, điểm phù hợp %, lý do |
| UC25 | `POST /api/ai/chat` | Đã đăng nhập | Câu trả lời, trạng thái hiểu câu hỏi, gợi ý tiếp theo |
| UC26 | `GET /api/ai/demand-forecast?date=YYYY-MM-DD` | Admin | Công suất dự báo theo khung giờ, mức nhu cầu, khuyến nghị |
| UC27 | `GET /api/ai/promotions/me` | Đã đăng nhập | Phân nhóm và ưu đãi cá nhân |
| UC27 | `POST /api/ai/promotions/customers/{id}` | Admin | Tạo/cập nhật gợi ý cho khách |
| UC28 | `POST /api/ai/reviews/analyze` | Admin | Số lượng cảm xúc và vấn đề tiêu cực phổ biến |

## Tham số UC24

- `area`: khu vực/chuỗi có trong địa chỉ.
- `max_price`: ngân sách tối đa cho một khung giờ.
- `time_slot_id`: khung giờ mong muốn.
- `limit`: 1–20, mặc định 5.

Điểm phù hợp được tính từ lịch sử sân (tối đa 30), khu vực (20), ngân sách (20), khung giờ yêu thích (10), đánh giá (20) và điểm nền (20). `data_sufficient=false` cho biết hệ thống đang dùng danh sách dự phòng vì khách chưa có lịch sử hoặc bộ lọc tìm kiếm.

## Database

- `chatbot_logs`: nhật ký chuyên biệt của chatbot, lưu câu hỏi, câu trả lời, engine, intent, latency, session đã hash và lỗi nếu có. Lịch sử hội thoại gần nhất được đọc từ bảng này.
- `ai_interactions`: audit log yêu cầu/kết quả chatbot và gợi ý sân, gồm latency và trạng thái lỗi.
- `ai_review_analyses`: kết quả cảm xúc/topics theo từng review; khóa duy nhất `review_id` giúp chạy lại an toàn.
- `ai_demand_forecasts`: snapshot dự báo theo ngày và khung giờ.
- `ai_promotion_recommendations`: phân nhóm `NEW`, `VIP`, `INACTIVE`, `ACTIVE` và ưu đãi tương ứng.

Chatbot và phân tích đánh giá dùng OpenAI Responses API với Structured Outputs khi có `OPENAI_API_KEY`. Hệ thống đặt `store=false`, chỉ gửi câu hỏi cùng dữ liệu nghiệp vụ cần thiết, và tự động dùng engine luật (`rules-v1`) khi thiếu key, timeout hoặc API lỗi.

Chatbot dùng knowledge context được tạo ở backend từ sân đang hoạt động, bảng giá, đánh giá, khuyến mãi, quy trình đặt sân và tối đa 5 booking gần nhất của đúng khách hàng. Sáu lượt hội thoại gần nhất được lấy từ `ai_interactions` và gửi lại theo kiểu stateless để hiểu câu hỏi nối tiếp. `safety_identifier` là hash nội bộ, không gửi email hoặc số điện thoại tới OpenAI.

```env
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4o-mini
OPENAI_BASE_URL=https://api.openai.com/v1
OPENAI_TIMEOUT=15
```

Sau khi sửa `.env`, chạy `php artisan config:clear`. Không commit API key vào Git.
