<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\AiKnowledgeService;
use App\Services\LlmClientService;
use App\Services\SmashBotPromptService;
use App\Services\SmashZoneToolRegistry;
use App\Services\ToolCallingAgentService;
use Tests\TestCase;

class ToolCallingAgentServiceTest extends TestCase
{
    public function test_it_executes_an_allowlisted_tool_and_returns_the_final_message(): void
    {
        $llm = $this->createMock(LlmClientService::class);
        $registry = $this->createMock(SmashZoneToolRegistry::class);
        $knowledge = $this->createMock(AiKnowledgeService::class);

        $registry->method('definitions')->willReturn([['type' => 'function', 'name' => 'get_promotions']]);
        $registry->expects($this->once())->method('execute')->with('get_promotions', [], $this->isInstanceOf(User::class))
            ->willReturn(['ok' => true, 'promotions' => [['title' => 'Ưu đãi tối']]]);
        $knowledge->method('recentConversation')->willReturn([]);
        $llm->expects($this->exactly(2))->method('toolTurn')->willReturnOnConsecutiveCalls(
            ['output' => [['type' => 'function_call', 'name' => 'get_promotions', 'arguments' => '{}', 'call_id' => 'call_1']]],
            ['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Hiện có ưu đãi tối.']]]]],
        );
        $agent = new ToolCallingAgentService($llm, $registry, new SmashBotPromptService, $knowledge);

        $result = $agent->answer('Có khuyến mãi không?', new User(['id' => 7]));

        $this->assertSame('Hiện có ưu đãi tối.', $result['answer']);
        $this->assertSame('get_promotions', $result['tool_trace'][0]['tool']);
        $this->assertTrue($result['tool_trace'][0]['ok']);
    }

    public function test_it_asks_the_llm_without_tools_when_the_question_is_general_knowledge(): void
    {
        $llm = $this->createMock(LlmClientService::class);
        $registry = $this->createMock(SmashZoneToolRegistry::class);
        $knowledge = $this->createMock(AiKnowledgeService::class);

        $registry->method('definitions')->willReturn([]);
        $registry->expects($this->never())->method('execute');
        $knowledge->method('recentConversation')->willReturn([]);
        $llm->expects($this->once())->method('toolTurn')->willReturn(
            ['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Sân cầu lông dài 13,4m.']]]]],
        );

        $agent = new ToolCallingAgentService($llm, $registry, new SmashBotPromptService, $knowledge);
        $result = $agent->answer('Sân cầu lông dài bao nhiêu mét?', new User(['id' => 7]));

        $this->assertSame('Sân cầu lông dài 13,4m.', $result['answer']);
        $this->assertSame([], $result['tool_trace']);
    }

    public function test_guest_agent_never_receives_account_only_tools(): void
    {
        $llm = $this->createMock(LlmClientService::class);
        $registry = $this->createMock(SmashZoneToolRegistry::class);
        $knowledge = $this->createMock(AiKnowledgeService::class);

        $registry->method('definitions')->willReturn([
            ['type' => 'function', 'name' => 'get_promotions'],
            ['type' => 'function', 'name' => 'get_my_booking'],
            ['type' => 'function', 'name' => 'get_my_notifications'],
        ]);
        $registry->expects($this->never())->method('execute');
        $knowledge->method('recentConversation')->with(null)->willReturn([]);
        $llm->expects($this->once())->method('toolTurn')->with(
            $this->anything(),
            $this->callback(fn (array $tools) => collect($tools)->pluck('name')->all() === ['get_promotions']),
            null,
            $this->stringContains('CHƯA ĐĂNG NHẬP'),
        )->willReturn([
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Câu trả lời công khai.']]]],
        ]);

        $agent = new ToolCallingAgentService($llm, $registry, new SmashBotPromptService, $knowledge);
        $result = $agent->answer('Một câu hỏi chung', null);

        $this->assertSame('Câu trả lời công khai.', $result['answer']);
    }
}
