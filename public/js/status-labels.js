(() => {
    const labels = {
        ACTIVE: 'Đang hoạt động', INACTIVE: 'Ngừng hoạt động', LOCKED: 'Đã khóa',
        AVAILABLE: 'Sẵn sàng', OCCUPIED: 'Đang sử dụng', MAINTENANCE: 'Bảo trì',
        BOOKED: 'Đã được đặt', HOLD: 'Đang giữ chỗ', PENDING: 'Đang chờ xử lý',
        PENDING_PAYMENT: 'Chờ thanh toán', PAID: 'Đã thanh toán', CONFIRMED: 'Đã xác nhận',
        CHECKED_IN: 'Đã nhận sân', COMPLETED: 'Đã hoàn thành', CANCELLED: 'Đã hủy',
        EXPIRED: 'Đã hết hạn', FAILED: 'Thất bại', REFUNDED: 'Đã hoàn tiền',
        PROCESSING: 'Đang xử lý', APPROVED: 'Đã phê duyệt', REJECTED: 'Đã từ chối',
        NEEDS_INFO: 'Cần bổ sung thông tin', ADMIN: 'Quản trị viên', EMPLOYEE: 'Nhân viên',
        CUSTOMER: 'Khách hàng', WEEKDAY: 'Ngày thường', WEEKEND: 'Cuối tuần', HOLIDAY: 'Ngày lễ',
        PERCENTAGE: 'Phần trăm', FIXED: 'Số tiền cố định', UPDATED: 'Đã cập nhật',
        CREATED: 'Đã tạo', MARK_PAID: 'Xác nhận thanh toán', MARK_FAILED: 'Đánh dấu thất bại'
    };
    const pattern = new RegExp(`\\b(${Object.keys(labels).sort((a, b) => b.length - a.length).join('|')})\\b`, 'g');
    const translate = (root = document.body) => {
        // Some legacy <option> elements derive their submitted value from their
        // visible text. Preserve that value before translating the label.
        const optionValues = new Map(
            [...root.querySelectorAll('option')].map(option => [option, option.value])
        );
        const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
            acceptNode(node) {
                return ['SCRIPT', 'STYLE', 'TEXTAREA'].includes(node.parentElement?.tagName)
                    ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT;
            }
        });
        let node;
        while ((node = walker.nextNode())) node.nodeValue = node.nodeValue.replace(pattern, key => labels[key]);
        optionValues.forEach((value, option) => { option.value = value; });
    };
    document.readyState === 'loading'
        ? document.addEventListener('DOMContentLoaded', () => translate()) : translate();
})();
