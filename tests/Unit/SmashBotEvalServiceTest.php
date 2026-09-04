<?php

namespace Tests\Unit;

use App\Services\SmashBotEvalDataset;
use App\Services\SmashBotEvalService;
use Tests\TestCase;

class SmashBotEvalServiceTest extends TestCase
{
    public function test_dataset_contains_exactly_150_vietnamese_scenarios(): void
    {
        $cases = app(SmashBotEvalDataset::class)->cases();

        $this->assertCount(150, $cases);
        $this->assertSame([
            'intent' => 60,
            'multi_intent' => 25,
            'prompt_injection' => 25,
            'privacy' => 20,
            'normal_safety' => 20,
        ], collect($cases)->countBy('category')->all());
    }

    public function test_offline_eval_returns_an_explainable_quality_score(): void
    {
        $result = app(SmashBotEvalService::class)->run();

        $this->assertSame(150, $result['total']);
        $this->assertArrayHasKey('quality_score', $result);
        $this->assertArrayHasKey('prompt_injection', $result['category_scores']);
        $this->assertArrayHasKey('privacy', $result['category_scores']);
        $this->assertIsArray($result['failures']);
    }
}
