<?php

namespace Tests\Unit;

use App\Services\OpenAiService;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class OpenAiServiceTest extends TestCase
{
    public function test_chatbot_uses_responses_api_structured_output(): void
    {
        config()->set('services.openai', [
            'api_key' => 'test-key',
            'model' => 'gpt-4o-mini',
            'base_url' => 'https://api.openai.com/v1',
            'timeout' => 5,
        ]);
        Http::fake([
            'api.openai.com/v1/responses' => Http::response([
                'output' => [[
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode(['understood' => true, 'answer' => 'Giá từ 100.000đ.', 'suggestions' => ['Tìm sân', 'Xem khuyến mãi'], 'intent' => 'COURT_PRICE']),
                    ]],
                ]],
            ]),
        ]);

        $result = app(OpenAiService::class)->chatbot(
            'Giá sân?',
            ['price_from' => 100000],
            [['role' => 'user', 'content' => 'Tôi muốn tìm sân']],
            123
        );

        $this->assertTrue($result['understood']);
        $this->assertSame('Giá từ 100.000đ.', $result['answer']);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/responses'
            && $request['store'] === false
            && is_array($request['input'])
            && count($request['input']) === 2
            && $request['safety_identifier'] === hash('sha256', 'smashzone-user-123')
            && $request['text']['format']['type'] === 'json_schema'
            && str_contains($request['instructions'], 'Không tự bịa giá, sân, lịch trống')
            && in_array('CHECK_AVAILABILITY', $request['text']['format']['schema']['properties']['intent']['enum'], true)
            && $request->hasHeader('Authorization', 'Bearer test-key'));
    }

    public function test_rate_limit_is_retried(): void
    {
        config()->set('services.openai', [
            'api_key' => 'test-key',
            'model' => 'gpt-5.6-luna',
            'base_url' => 'https://api.openai.com/v1',
            'timeout' => 5,
            'attempts' => 3,
        ]);

        Http::fakeSequence()
            ->push(['error' => ['code' => 'rate_limit_exceeded']], 429)
            ->push(['output' => [[
                'content' => [[
                    'type' => 'output_text',
                    'text' => json_encode([
                        'understood' => true,
                        'answer' => 'Đã thử lại thành công.',
                        'suggestions' => ['Tìm sân', 'Xem giá'],
                        'intent' => 'FAQ',
                    ]),
                ]],
            ]]], 200);

        $result = app(OpenAiService::class)->chatbot('Xin chào', []);
        $this->assertSame('Đã thử lại thành công.', $result['answer']);
        Http::assertSentCount(2);
    }

    public function test_intent_classifier_uses_strict_structured_output(): void
    {
        config()->set('services.openai', [
            'api_key' => 'test-key',
            'model' => 'gpt-5.6-luna',
            'base_url' => 'https://api.openai.com/v1',
            'timeout' => 5,
            'attempts' => 1,
        ]);
        $classification = [
            'intent' => 'CHECK_AVAILABILITY',
            'date' => now()->addDay()->toDateString(),
            'hour' => 19,
            'area' => 'Cầu Giấy',
            'court_name' => null,
            'booking_code' => null,
            'service_name' => null,
            'limit' => null,
            'confidence' => 0.98,
        ];
        Http::fake(['*' => Http::response(['output' => [[
            'content' => [['type' => 'output_text', 'text' => json_encode($classification)]],
        ]]], 200)]);

        $result = app(OpenAiService::class)->classifyIntent('Tối mai 19h ở Cầu Giấy còn sân không?', 10);

        $this->assertSame($classification, $result);
        Http::assertSent(fn ($request) => $request['text']['format']['name'] === 'smashzone_intent'
            && $request['text']['format']['strict'] === true
            && $request['max_output_tokens'] === 500);
    }

    public function test_quota_error_is_not_retried(): void
    {
        config()->set('services.openai', [
            'api_key' => 'test-key',
            'model' => 'gpt-5.6-luna',
            'base_url' => 'https://api.openai.com/v1',
            'timeout' => 5,
            'attempts' => 3,
        ]);

        Http::fake([
            '*' => Http::response(['error' => ['code' => 'insufficient_quota']], 429),
        ]);

        try {
            app(OpenAiService::class)->chatbot('Xin chào', []);
            $this->fail('Expected a quota exception.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('quota', $exception->getMessage());
        }

        Http::assertSentCount(1);
    }

    public function test_embeddings_use_configured_embedding_model(): void
    {
        config()->set('services.openai', [
            'api_key' => 'test-key',
            'embedding_model' => 'text-embedding-3-small',
            'base_url' => 'https://api.openai.com/v1',
            'timeout' => 5,
        ]);
        Http::fake(['*' => Http::response(['data' => [
            ['index' => 0, 'embedding' => [0.1, 0.2]],
            ['index' => 1, 'embedding' => [0.3, 0.4]],
        ]], 200)]);

        $vectors = app(OpenAiService::class)->embeddings(['FAQ một', 'FAQ hai']);

        $this->assertSame([[0.1, 0.2], [0.3, 0.4]], $vectors);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/embeddings'
            && $request['model'] === 'text-embedding-3-small'
            && $request['input'] === ['FAQ một', 'FAQ hai']);
    }

    public function test_tool_turn_sends_strict_function_tools_to_responses_api(): void
    {
        config()->set('services.openai', [
            'api_key' => 'test-key', 'model' => 'gpt-5.6-luna',
            'base_url' => 'https://api.openai.com/v1', 'timeout' => 5, 'attempts' => 1,
        ]);
        Http::fake(['*' => Http::response(['output' => [[
            'type' => 'function_call', 'name' => 'get_promotions', 'arguments' => '{}', 'call_id' => 'call_1',
        ]]], 200)]);
        $tools = [[
            'type' => 'function', 'name' => 'get_promotions', 'description' => 'Get promotions', 'strict' => true,
            'parameters' => ['type' => 'object', 'properties' => [], 'required' => [], 'additionalProperties' => false],
        ]];

        $result = app(OpenAiService::class)->toolTurn([['role' => 'user', 'content' => 'Có ưu đãi không?']], $tools, 9);

        $this->assertSame('get_promotions', $result['output'][0]['name']);
        Http::assertSent(fn ($request) => $request['tools'] === $tools
            && $request['tool_choice'] === 'auto'
            && $request['parallel_tool_calls'] === false
            && $request['store'] === false);
    }
}
