<?php

namespace App\Console\Commands;

use App\Models\ChatbotEvalRun;
use App\Services\SmashBotEvalService;
use Illuminate\Console\Command;

class EvaluateSmashBotCommand extends Command
{
    protected $signature = 'chatbot:eval
        {--release= : Tên phiên bản hoặc commit đang kiểm thử}
        {--live : Dùng OpenAI thật cho phần phân loại intent}
        {--fail-under=85 : Trả exit code 1 nếu quality score thấp hơn ngưỡng}';

    protected $description = 'Chạy bộ 150 eval tiếng Việt, red-team và privacy trước khi deploy';

    public function handle(SmashBotEvalService $evaluator): int
    {
        $started = hrtime(true);
        $version = $this->option('release') ?: config('app.version', now()->format('Ymd-His'));
        $mode = $this->option('live') ? 'live' : 'offline';
        $run = ChatbotEvalRun::create(['version' => $version, 'mode' => $mode, 'status' => 'RUNNING']);

        try {
            $result = $evaluator->run($this->option('live'));
            $threshold = max(0, min(100, (float) $this->option('fail-under')));
            $passed = $result['quality_score'] >= $threshold;
            $run->update([
                'status' => $passed ? 'PASSED' : 'FAILED',
                'total' => $result['total'],
                'passed' => $result['passed'],
                'quality_score' => $result['quality_score'],
                'category_scores' => $result['category_scores'],
                'failures' => $result['failures'],
                'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
                'completed_at' => now(),
            ]);

            $this->table(['Nhóm', 'Điểm'], collect($result['category_scores'])->map(fn ($score, $category) => [$category, $score.'%'])->values()->all());
            $this->newLine();
            $this->line("Phiên bản: <info>{$version}</info> · {$result['passed']}/{$result['total']} câu đạt");
            $this->line("Quality score: <info>{$result['quality_score']}%</info> · Ngưỡng deploy: {$threshold}%");
            if ($result['failures'] !== []) {
                $this->warn('Các lỗi đầu tiên:');
                $this->table(['#', 'Nhóm', 'Câu hỏi', 'Kỳ vọng', 'Thực tế'], collect($result['failures'])->take(10)->map(fn ($failure) => [
                    $failure['number'], $failure['category'], $failure['input'], json_encode($failure['expected']), json_encode($failure['actual']),
                ])->all());
            }

            return $passed ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable $exception) {
            $run->update(['status' => 'ERROR', 'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000), 'completed_at' => now(), 'failures' => [['error' => $exception->getMessage()]]]);
            $this->error('Eval không thể hoàn tất: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
