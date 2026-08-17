<?php

namespace Tests\Unit;

use App\Support\StatusLabel;
use PHPUnit\Framework\TestCase;

class StatusLabelTest extends TestCase
{
    public function test_system_statuses_have_vietnamese_labels(): void
    {
        $this->assertSame('Đang hoạt động', StatusLabel::get('ACTIVE'));
        $this->assertSame('Chờ thanh toán', StatusLabel::get('PENDING_PAYMENT'));
        $this->assertSame('Đã nhận sân', StatusLabel::get('CHECKED_IN'));
        $this->assertSame('Cần bổ sung thông tin', StatusLabel::get('NEEDS_INFO'));
        $this->assertSame('Quản trị viên', StatusLabel::get('ADMIN'));
    }
}
