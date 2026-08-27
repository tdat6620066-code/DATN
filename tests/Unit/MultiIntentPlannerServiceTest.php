<?php

namespace Tests\Unit;

use App\Services\AvailableCourtService;
use App\Services\MultiIntentPlannerService;
use App\Services\OpenAiService;
use Tests\TestCase;

class MultiIntentPlannerServiceTest extends TestCase
{
    public function test_it_detects_a_multi_constraint_booking_request(): void
    {
        $planner = new MultiIntentPlannerService(
            $this->createMock(OpenAiService::class),
            $this->createMock(AvailableCourtService::class),
        );

        $this->assertTrue($planner->shouldHandle('Mai 7h tìm sân Cầu Giấy dưới 150k, nếu còn thì đặt luôn'));
    }

    public function test_it_keeps_revision_messages_in_the_active_plan(): void
    {
        session(['chatbot.search_plan' => ['date' => today()->addDay()->toDateString(), 'hour' => 7]]);
        $planner = new MultiIntentPlannerService(
            $this->createMock(OpenAiService::class),
            $this->createMock(AvailableCourtService::class),
        );

        $this->assertTrue($planner->shouldHandle('Thôi chuyển sang 8h'));
        $this->assertTrue($planner->shouldHandle('Rẻ hơn nữa'));
    }

    public function test_unrelated_message_does_not_start_a_plan(): void
    {
        session()->forget('chatbot.search_plan');
        $planner = new MultiIntentPlannerService(
            $this->createMock(OpenAiService::class),
            $this->createMock(AvailableCourtService::class),
        );

        $this->assertFalse($planner->shouldHandle('Tôi quên mật khẩu'));
    }
}
