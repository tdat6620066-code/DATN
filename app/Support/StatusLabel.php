<?php

namespace App\Support;

class StatusLabel
{
    private const LABELS = [
        'ACTIVE' => 'Đang hoạt động', 'INACTIVE' => 'Ngừng hoạt động', 'LOCKED' => 'Đã khóa',
        'AVAILABLE' => 'Sẵn sàng', 'OCCUPIED' => 'Đang sử dụng', 'MAINTENANCE' => 'Bảo trì',
        'BOOKED' => 'Đã được đặt', 'HOLD' => 'Đang giữ chỗ',
        'PENDING' => 'Đang chờ xử lý', 'PENDING_PAYMENT' => 'Chờ thanh toán',
        'PAID' => 'Đã thanh toán', 'CONFIRMED' => 'Đã xác nhận', 'CHECKED_IN' => 'Đã nhận sân',
        'COMPLETED' => 'Đã hoàn thành', 'CANCELLED' => 'Đã hủy', 'EXPIRED' => 'Đã hết hạn',
        'FAILED' => 'Thất bại', 'REFUNDED' => 'Đã hoàn tiền', 'PROCESSING' => 'Đang xử lý',
        'APPROVED' => 'Đã phê duyệt', 'REJECTED' => 'Đã từ chối', 'NEEDS_INFO' => 'Cần bổ sung thông tin',
        'ADMIN' => 'Quản trị viên', 'EMPLOYEE' => 'Nhân viên', 'CUSTOMER' => 'Khách hàng',
        'WEEKDAY' => 'Ngày thường', 'WEEKEND' => 'Cuối tuần', 'HOLIDAY' => 'Ngày lễ',
        'PERCENTAGE' => 'Phần trăm', 'FIXED' => 'Số tiền cố định',
        'UPDATED' => 'Đã cập nhật', 'CREATED' => 'Đã tạo', 'MARK_PAID' => 'Xác nhận thanh toán',
        'MARK_FAILED' => 'Đánh dấu thất bại',
    ];

    public static function get(?string $value): string
    {
        return blank($value) ? '—' : (self::LABELS[$value] ?? str_replace('_', ' ', $value));
    }
}
