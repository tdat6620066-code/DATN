<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\AvailableCourtService;
use App\Services\ChatbotService;
use App\Services\DatabaseChatService;
use App\Services\IntentClassifierService;
use App\Services\RagService;
use Mockery;
use Tests\TestCase;

class ChatbotServiceTest extends TestCase
{
    public function test_it_normalizes_database_intents_into_the_chatbot_pipeline(): void
    {
        $database = Mockery::mock(DatabaseChatService::class);
        $classifier = Mockery::mock(IntentClassifierService::class);
        $availability = Mockery::mock(AvailableCourtService::class);
        $rag = Mockery::mock(RagService::class);
        $user = new User(['name' => 'Khách hàng']);
        $user->id = 12;
        $rag->shouldReceive('search')->once()->with('Giá sân hôm nay?')->andReturn([]);

        $classification = [
            'intent' => 'COURT_PRICE', 'date' => null, 'hour' => null, 'area' => null,
            'court_name' => null, 'booking_code' => null, 'service_name' => null,
            'limit' => null, 'confidence' => 0.95, 'classifier' => 'test',
        ];
        $classifier->shouldReceive('classify')->once()->with('Giá sân hôm nay?', 12)->andReturn($classification);
        $database->shouldReceive('answerClassified')
            ->once()
            ->with($classification, 'Giá sân hôm nay?', $user)
            ->andReturn([
                'understood' => true,
                'answer' => 'Giá lấy từ MySQL.',
                'intent' => 'PRICE',
                'suggestions' => [],
            ]);

        $result = (new ChatbotService($classifier, $database, $availability, $rag))->answer('Giá sân hôm nay?', $user);

        $this->assertSame('COURT_PRICE', $result['intent']);
        $this->assertSame('PRICE', $result['intent_detail']);
        $this->assertSame('mysql', $result['pipeline_stage']);
        $this->assertFalse($result['context_used']);
    }

    public function test_high_confidence_semantic_faq_skips_classifier_and_generation(): void
    {
        config()->set('services.openai.rag_direct_threshold', 0.84);
        $database = Mockery::mock(DatabaseChatService::class);
        $classifier = Mockery::mock(IntentClassifierService::class);
        $availability = Mockery::mock(AvailableCourtService::class);
        $rag = Mockery::mock(RagService::class);
        $user = new User(['name' => 'Khách hàng']);
        $user->id = 15;

        $rag->shouldReceive('search')->once()->andReturn([[
            'source_type' => 'faq',
            'score' => 0.93,
            'metadata' => ['answer' => 'Bạn có thể thanh toán bằng VNPay.'],
        ]]);
        $classifier->shouldNotReceive('classify');
        $database->shouldNotReceive('answerClassified');

        $result = (new ChatbotService($classifier, $database, $availability, $rag))
            ->answer('Thanh toán bằng cách nào?', $user);

        $this->assertSame('semantic_rag_direct', $result['pipeline_stage']);
        $this->assertTrue($result['generation_skipped']);
        $this->assertSame('Bạn có thể thanh toán bằng VNPay.', $result['answer']);
    }
}
