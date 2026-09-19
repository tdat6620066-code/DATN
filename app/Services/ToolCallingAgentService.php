<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Agent tool calling của SmashBot — lớp xử lý MỌI câu hỏi người dùng.
 *
 * Vòng lặp: gửi câu hỏi + lịch sử hội thoại cho LLM (Groq hoặc OpenAI) kèm danh
 * sách tool; nếu model yêu cầu gọi tool thì thực thi tool đó trên hệ thống
 * (đọc dữ liệu thật qua API/DB), trả kết quả lại cho model, rồi lặp tới khi
 * model tổng hợp được câu trả lời cuối cùng.
 *
 * Bảo mật: tool luôn chạy trong phạm vi user đang đăng nhập; model không bao giờ
 * truyền được user_id. Kết quả tool là dữ liệu, không phải chỉ dẫn.
 */
class ToolCallingAgentService
{
    public function __construct(
        private readonly LlmClientService $llm,
        private readonly SmashZoneToolRegistry $tools,
        private readonly SmashBotPromptService $prompts,
        private readonly AiKnowledgeService $knowledge,
    ) {}

    /**
     * Từ khoá nhận biết câu hỏi cần dữ liệu SmashZone (sân, giá, lịch, booking...).
     */
    private const DATA_KEYWORDS = [
        'tim san', 'san trong', 'con san', 'con trong', 'khung gio', 'gio trong', 'lich trong',
        'gia san', 'gia thue', 'bang gia', 'bao nhieu tien', 'gia bao nhieu',
        'khuyen mai', 'voucher', 'giam gia', 'uu dai', 'ma giam',
        'booking', 'dat san', 'don cua toi', 'lich cua toi', 'kiem tra bk',
        'thanh toan', 'hoa don', 'huy san', 'huy booking', 'check in',
        'danh gia', 'review', 'nhan xet',
        'dich vu', 'thue vot', 'nuoc uong', 'do thue',
        'thong bao', 'dia chi', 'o dau', 'khu vuc', 'quan ',
        'tien ich', 'mo cua', 'dong cua', 'gop y', 'khieu nai',
    ];

    /**
     * Mọi câu hỏi người dùng nhập đều được agent xử lý khi bật chế độ always.
     */
    public function shouldHandle(string $message): bool
    {
        if (! config('chatbot.tool_calling_enabled', true) || ! $this->llm->configured()) {
            return false;
        }

        // Chế độ mặc định của SmashBot: mọi câu hỏi đều đi qua agent để có thể
        // trả lời tất cả câu hỏi, kể cả câu hỏi ngoài nghiệp vụ đặt sân.
        if (config('chatbot.tool_calling_always', true)) {
            return true;
        }

        return $this->needsLiveData($message);
    }

    /**
     * true khi câu hỏi cần dữ liệu thật của hệ thống.
     */
    public function needsLiveData(string $message): bool
    {
        return Str::contains(Str::lower(Str::ascii($message)), self::DATA_KEYWORDS);
    }

    /**
     * Danh sách tool gửi kèm mỗi lượt.
     *
     * @param  array<int, array<string, mixed>>  $definitions
     * @return array<int, array<string, mixed>>
     */
    private function toolsFor(string $message, array $definitions): array
    {
        if (! config('chatbot.tiered_tools', true) || $this->needsLiveData($message)) {
            return $definitions;
        }

        // Câu hỏi kiến thức chung: chỉ gửi tool tra cứu cơ bản để tiết kiệm
        // token/phút. Prompt cho phép model trả lời thẳng khi không cần tool,
        // nên model vẫn gọi tool nếu khách nhắc tới sân/giá/khuyến mãi.
        return collect($definitions)->filter(fn (array $tool) => in_array(
            $tool['name'] ?? null,
            ['search_courts', 'check_availability', 'get_promotions'],
            true,
        ))->values()->all();
    }

    public function answer(string $message, User $user): array
    {
        $definitions = $this->toolsFor($message, $this->tools->definitions());
        $instructions = $this->prompts->toolAgentSystem($definitions);

        $input = array_merge(
            $this->knowledge->recentConversation($user, (int) config('chatbot.history_turns', 6)),
            [['role' => 'user', 'content' => $message]],
        );

        $trace = [];
        $artifacts = ['cards' => [], 'buttons' => []];
        $maxRounds = max(1, (int) config('chatbot.max_tool_rounds', 4));

        for ($round = 1; $round <= $maxRounds; $round++) {
            $response = $this->llm->toolTurn($input, $definitions, $user->id, $instructions);
            $output = is_array($response['output'] ?? null) ? $response['output'] : [];
            $calls = collect($output)->where('type', 'function_call')->values();
            $text = $this->outputText($output);

            if ($calls->isEmpty()) {
                return $this->result(
                    $text ?: 'Mình chưa có đủ dữ liệu để trả lời yêu cầu này. Bạn hỏi cụ thể hơn giúp mình nhé.',
                    $artifacts,
                    $trace,
                );
            }

            $input = array_merge($input, $output);
            foreach ($calls as $call) {
                $input = $this->runTool($call, $round, $input, $trace, $artifacts, $user);
            }
        }

        // Hết số vòng cho phép: vẫn trả về câu trả lời có ích thay vì ném lỗi,
        // để chatbot không im lặng khi model gọi tool quá nhiều lần.
        return $this->result(
            'Mình đã kiểm tra dữ liệu nhưng cần bạn xác nhận thêm một thông tin. Bạn nêu rõ ngày, giờ hoặc tên sân giúp mình nhé.',
            $artifacts,
            $trace,
        );
    }

    /**
     * Thực thi một function_call của model và nối kết quả vào input của lượt sau.
     *
     * @param  array<string, mixed>  $call
     * @param  array<int, array<string, mixed>>  $input
     * @param  array<int, array<string, mixed>>  $trace  truyền tham chiếu để ghi lại dấu vết gọi tool
     * @param  array{cards: array<int, mixed>, buttons: array<int, mixed>}  $artifacts
     * @return array<int, array<string, mixed>>
     */
    private function runTool(array $call, int $round, array $input, array &$trace, array &$artifacts, User $user): array
    {
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
            'call_id' => $call['call_id'] ?? null,
            'output' => json_encode($this->safeToolOutput($result), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        return $input;
    }

    /**
     * @param  array{cards: array<int, mixed>, buttons: array<int, mixed>}  $artifacts
     * @param  array<int, array<string, mixed>>  $trace
     * @return array<string, mixed>
     */
    private function result(string $answer, array $artifacts, array $trace): array
    {
        return [
            'understood' => true,
            'answer' => $answer,
            'intent' => 'TOOL_AGENT',
            'suggestions' => ['Tìm sân trống ngày mai', 'Xem booking của tôi'],
            'cards' => $artifacts['cards'],
            'buttons' => $artifacts['buttons'],
            'tool_trace' => $trace,
            'engine' => $this->llm->provider().':'.$this->llm->model(),
            'pipeline_stage' => 'tool_calling_agent',
        ];
    }

    private function collectArtifacts(string $name, array $result, array &$artifacts): void
    {
        if ($name === 'search_courts') {
            $artifacts['cards'] = collect($result['courts'] ?? [])->map(fn ($court) => [
                'type' => 'court', 'title' => $court['name'], 'subtitle' => $court['address'],
                'price_from' => $court['price_from'], 'image_url' => $court['image_url'], 'url' => $court['url'],
            ])->all();
        }
        if ($name === 'get_court_info' && ($result['ok'] ?? false) && filled($result['court'] ?? null)) {
            $court = $result['court'];
            $artifacts['cards'] = [[
                'type' => 'court', 'title' => $court['name'], 'subtitle' => $court['address'],
                'meta' => $court['rating'] === null ? null : 'Đánh giá '.$court['rating'].'/5',
                'price_from' => $court['price_from'], 'rating' => $court['rating'], 'url' => $court['url'],
            ]];
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
