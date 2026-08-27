<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\OpenAiService;
use App\Services\SmashZoneToolRegistry;
use App\Services\ToolCallingAgentService;
use Tests\TestCase;

class ToolCallingAgentServiceTest extends TestCase
{
    public function test_it_executes_an_allowlisted_tool_and_returns_the_final_message(): void
    {
        $openai = $this->createMock(OpenAiService::class);
        $registry = $this->createMock(SmashZoneToolRegistry::class);
        $registry->method('definitions')->willReturn([['type' => 'function', 'name' => 'get_promotions']]);
        $registry->expects($this->once())->method('execute')->with('get_promotions', [], $this->isInstanceOf(User::class))
            ->willReturn(['ok' => true, 'promotions' => [['title' => 'Ưu đãi tối']]]);
        $openai->expects($this->exactly(2))->method('toolTurn')->willReturnOnConsecutiveCalls(
            ['output' => [['type' => 'function_call', 'name' => 'get_promotions', 'arguments' => '{}', 'call_id' => 'call_1']]],
            ['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Hiện có ưu đãi tối.']]]]],
        );
        $agent = new ToolCallingAgentService($openai, $registry);

        $result = $agent->answer('Có khuyến mãi không?', new User(['id' => 7]));

        $this->assertSame('Hiện có ưu đãi tối.', $result['answer']);
        $this->assertSame('get_promotions', $result['tool_trace'][0]['tool']);
        $this->assertTrue($result['tool_trace'][0]['ok']);
    }
}
