<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

class ToolCallingAgentService
{
    public function __construct(
        private readonly OpenAiService $openai,
        private readonly SmashZoneToolRegistry $tools,
    ) {}

    public function shouldHandle(string $message): bool
    {
        if (! config('chatbot.tool_calling_enabled', true) || ! $this->openai->configured()) {
            return false;
        }
        $text = Str::lower(Str::ascii($message));

        return Str::contains($text, [
            'tim san', 'san trong', 'con san', 'gia san', 'gia thue', 'khuyen mai',
            'voucher', 'booking cua toi', 'don cua toi', 'kiem tra bk', 'thanh toan chua',
        ]);
    }

    public function answer(string $message, User $user): array
    {
        $input = [['role' => 'user', 'content' => $message]];
        $trace = [];
        $artifacts = ['cards' => [], 'buttons' => []];

        for ($round = 1; $round <= 4; $round++) {
            $response = $this->openai->toolTurn($input, $this->tools->definitions(), $user->id);
            $output = $response['output'] ?? [];
            $calls = collect($output)->where('type', 'function_call')->values();
            if ($calls->isEmpty()) {
                return [
                    'understood' => true,
                    'answer' => $this->outputText($output) ?: 'Mình chưa có đủ dữ liệu để trả lời yêu cầu này.',
                    'intent' => 'TOOL_AGENT',
                    'suggestions' => ['Tìm sân trống ngày mai', 'Xem booking của tôi'],
                    'cards' => $artifacts['cards'],
                    'buttons' => $artifacts['buttons'],
                    'tool_trace' => $trace,
                    'engine' => config('services.openai.model'),
                    'pipeline_stage' => 'tool_calling_agent',
                ];
            }

            $input = array_merge($input, $output);
            foreach ($calls as $call) {
                $name = (string) ($call['name'] ?? '');
                $arguments = json_decode((string) ($call['arguments'] ?? '{}'), true);
                if (! is_array($arguments)) {
                    $arguments = [];
                }
                try {
                    $result = $this->tools->execute($name, $arguments, $user);
                } catch (\Throwable $exception) {
                    $result = ['ok' => false, 'error' => 'Tham số tool không hợp lệ hoặc thao tác không được phép.'];
                    report($exception);
                }
                $trace[] = ['round' => $round, 'tool' => $name, 'arguments' => $arguments, 'ok' => $result['ok'] ?? false];
                $this->collectArtifacts($name, $result, $artifacts);
                $input[] = [
                    'type' => 'function_call_output',
                    'call_id' => $call['call_id'],
                    'output' => json_encode($this->safeToolOutput($result), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ];
            }
        }

        throw new \RuntimeException('Tool calling exceeded the maximum of 4 rounds.');
    }

    private function collectArtifacts(string $name, array $result, array &$artifacts): void
    {
        if ($name === 'search_courts') {
            $artifacts['cards'] = collect($result['courts'] ?? [])->map(fn ($court) => [
                'type' => 'court', 'title' => $court['name'], 'subtitle' => $court['address'],
                'price_from' => $court['price_from'], 'image_url' => $court['image_url'], 'url' => $court['url'],
            ])->all();
        }
        if ($name === 'check_availability') {
            $artifacts['cards'] = $result['cards'] ?? [];
            $artifacts['buttons'] = $result['buttons'] ?? [];
        }
        if ($name === 'prepare_booking') {
            $artifacts['buttons'] = $result['buttons'] ?? [];
        }
    }

    private function safeToolOutput(array $result): array
    {
        return collect($result)->except(['cards', 'buttons'])->all();
    }

    private function outputText(array $output): ?string
    {
        foreach ($output as $item) {
            foreach (($item['content'] ?? []) as $content) {
                if (($content['type'] ?? null) === 'output_text' && filled($content['text'] ?? null)) {
                    return $content['text'];
                }
            }
        }

        return null;
    }
}
