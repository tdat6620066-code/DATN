<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('group_code', 50)->index();
            $table->string('status_code', 50);
            $table->string('label_vi', 150);
            $table->string('description_vi', 500)->nullable();
            $table->timestamps();
            $table->unique(['group_code', 'status_code']);
        });

        $now = now();
        $definitions = [
            ['ACCOUNT', 'ACTIVE', 'Đang hoạt động', 'Tài khoản được phép đăng nhập và sử dụng hệ thống.'],
            ['ACCOUNT', 'INACTIVE', 'Ngừng hoạt động', 'Tài khoản đã ngừng hoạt động.'],
            ['ACCOUNT', 'LOCKED', 'Đã khóa', 'Tài khoản bị khóa và không được phép đăng nhập.'],
            ['ROLE', 'ADMIN', 'Quản trị viên', 'Có quyền quản trị toàn bộ hệ thống.'],
            ['ROLE', 'EMPLOYEE', 'Nhân viên', 'Thực hiện nghiệp vụ theo các quyền được cấp.'],
            ['ROLE', 'CUSTOMER', 'Khách hàng', 'Được tìm kiếm và đặt sân.'],
            ['BOOKING', 'PENDING_PAYMENT', 'Chờ thanh toán', 'Booking đã tạo và đang chờ khách hàng thanh toán.'],
            ['BOOKING', 'CONFIRMED', 'Đã xác nhận', 'Booking đã được thanh toán hoặc xác nhận hợp lệ.'],
            ['BOOKING', 'CHECKED_IN', 'Đã nhận sân', 'Khách hàng đã check-in và đang sử dụng sân.'],
            ['BOOKING', 'COMPLETED', 'Đã hoàn thành', 'Khách hàng đã check-out và booking kết thúc.'],
            ['BOOKING', 'CANCELLED', 'Đã hủy', 'Booking đã được hủy.'],
            ['BOOKING', 'EXPIRED', 'Đã hết hạn', 'Booking hết thời gian giữ chỗ hoặc thanh toán.'],
            ['PAYMENT', 'PENDING', 'Đang chờ thanh toán', 'Giao dịch đang chờ xử lý.'],
            ['PAYMENT', 'PAID', 'Đã thanh toán', 'Giao dịch đã thanh toán thành công.'],
            ['PAYMENT', 'FAILED', 'Thanh toán thất bại', 'Giao dịch thanh toán không thành công.'],
            ['PAYMENT', 'REFUNDED', 'Đã hoàn tiền', 'Giao dịch đã được hoàn tiền.'],
            ['REFUND_REQUEST', 'PENDING', 'Đang chờ xử lý', 'Yêu cầu đang chờ nhân viên kiểm tra.'],
            ['REFUND_REQUEST', 'NEEDS_INFO', 'Cần bổ sung thông tin', 'Khách hàng cần bổ sung thông tin cho yêu cầu.'],
            ['REFUND_REQUEST', 'APPROVED', 'Đã phê duyệt', 'Yêu cầu hoàn tiền đã được phê duyệt.'],
            ['REFUND_REQUEST', 'REJECTED', 'Đã từ chối', 'Yêu cầu hoàn tiền không được chấp thuận.'],
            ['REFUND', 'PROCESSING', 'Đang hoàn tiền', 'Khoản hoàn tiền đang được xử lý.'],
            ['REFUND', 'COMPLETED', 'Hoàn tiền thành công', 'Khoản tiền đã được hoàn cho khách hàng.'],
            ['REFUND', 'FAILED', 'Hoàn tiền thất bại', 'Quá trình hoàn tiền không thành công.'],
            ['COURT', 'ACTIVE', 'Đang hoạt động', 'Sân đang hoạt động và có thể được đặt.'],
            ['COURT', 'INACTIVE', 'Ngừng hoạt động', 'Sân đã ngừng hoạt động và không nhận booking mới.'],
            ['COURT_OPERATION', 'AVAILABLE', 'Sẵn sàng', 'Sân sẵn sàng phục vụ khách hàng.'],
            ['COURT_OPERATION', 'OCCUPIED', 'Đang sử dụng', 'Sân đang có khách sử dụng.'],
            ['COURT_OPERATION', 'LOCKED', 'Đã khóa', 'Sân tạm khóa theo quyết định vận hành.'],
            ['COURT_OPERATION', 'MAINTENANCE', 'Đang bảo trì', 'Sân không khả dụng trong thời gian bảo trì.'],
            ['AVAILABILITY', 'AVAILABLE', 'Còn trống', 'Khung giờ có thể được đặt.'],
            ['AVAILABILITY', 'BOOKED', 'Đã được đặt', 'Khung giờ đã có booking.'],
            ['AVAILABILITY', 'HOLD', 'Đang giữ chỗ', 'Khung giờ đang được tạm giữ để thanh toán.'],
            ['AVAILABILITY', 'MAINTENANCE', 'Bảo trì', 'Sân bảo trì trong khung giờ này.'],
            ['MAINTENANCE', 'ACTIVE', 'Đang bảo trì', 'Lịch bảo trì đang có hiệu lực.'],
            ['MAINTENANCE', 'COMPLETED', 'Đã hoàn thành', 'Lịch bảo trì đã kết thúc.'],
            ['MAINTENANCE', 'CANCELLED', 'Đã hủy', 'Lịch bảo trì đã bị hủy.'],
            ['VOUCHER', 'ACTIVE', 'Đang hoạt động', 'Voucher đang trong trạng thái cho phép áp dụng.'],
            ['VOUCHER', 'INACTIVE', 'Ngừng hoạt động', 'Voucher đã bị vô hiệu hóa.'],
            ['DAY_TYPE', 'WEEKDAY', 'Ngày thường', 'Ngày làm việc thông thường.'],
            ['DAY_TYPE', 'WEEKEND', 'Cuối tuần', 'Thứ Bảy hoặc Chủ nhật.'],
            ['DAY_TYPE', 'HOLIDAY', 'Ngày lễ', 'Ngày lễ được cấu hình trong hệ thống.'],
        ];

        DB::table('status_definitions')->insert(array_map(fn ($item) => [
            'group_code' => $item[0], 'status_code' => $item[1],
            'label_vi' => $item[2], 'description_vi' => $item[3],
            'created_at' => $now, 'updated_at' => $now,
        ], $definitions));
    }

    public function down(): void
    {
        Schema::dropIfExists('status_definitions');
    }
};
