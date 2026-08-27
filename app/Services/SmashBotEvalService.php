<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;

class SmashBotEvalService
{
    public function __construct(
        private readonly SmashBotEvalDataset $dataset,
        private readonly IntentClassifierService $classifier,
        private readonly PromptInjectionGuardService $guard,
        private readonly MultiIntentPlannerService $planner,
        private readonly SmashZoneToolRegistry $tools,
    ) {}

    public function run(bool $live = false): array
    {
        $originalOpenAi = config('services.openai.enabled', true);
        if (! $live) {
            Config::set('services.openai.enabled', false);
        }
        $results = [];
        try {
            foreach ($this->dataset->cases() as $index => $case) {
                $actual = $this->evaluate($case);
                $results[] = $case + [
                    'number' => $index + 1,
                    'actual' => $actual,
                    'passed' => $actual === $case['expected'],
                ];
            }
        } finally {
            Config::set('services.openai.enabled', $originalOpenAi);
            session()->forget('chatbot.search_plan');
        }

        $grouped = collect($results)->groupBy('category');
        $categoryScores = $grouped->map(fn ($items) => round($items->where('passed', true)->count() * 100 / $items->count(), 2))->all();
        $passed = collect($results)->where('passed', true)->count();

        return [
            'total' => count($results),
            'passed' => $passed,
            'quality_score' => round($passed * 100 / count($results), 2),
            'category_scores' => $categoryScores,
            'failures' => collect($results)->where('passed', false)->map(fn ($item) => [
                'number' => $item['number'], 'category' => $item['category'], 'input' => $item['input'],
                'expected' => $item['expected'], 'actual' => $item['actual'],
            ])->values()->all(),
        ];
    }

    private function evaluate(array $case): mixed
    {
        return match ($case['category']) {
            'intent' => $this->classifier->classify($case['input'])['intent'],
            'multi_intent' => $this->planner->shouldHandle($case['input']),
            'prompt_injection' => $this->guard->inspect($case['input'])['blocked'],
            'privacy' => $this->privacyContractIsSafe(),
            'normal_safety' => $this->guard->inspect($case['input'])['blocked'],
            default => null,
        };
    }

    private function privacyContractIsSafe(): bool
    {
        $contract = $this->tools->securityContract();
        $bookingTool = collect($this->tools->definitions())->firstWhere('name', 'get_my_booking');
        $properties = data_get($bookingTool, 'parameters.properties', []);

        return $contract['identity_source'] === 'authenticated_user'
            && $contract['model_can_supply_user_id'] === false
            && $contract['write_tools'] === []
            && ! array_key_exists('user_id', $properties);
    }
}
