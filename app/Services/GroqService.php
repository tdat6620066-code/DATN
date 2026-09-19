<?php

namespace App\Services;

use App\Exceptions\GroqRequestException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;


class GroqService
{
    public function configured(): bool
    {
        return (bool) config('services.groq.enabled', true) && filled(config('services.groq.api_key'));
    }

    public function model(): string
    {
        return (string) config('services.groq.model', 'openai/gpt-oss-120b');
    }

  
    private function supportsReasoningEffort(): bool
    {
        return str_contains(mb_strtolower($this->model()), 'gpt-oss');
    }

    /**
     * Gọi /chat/completions và chuẩn hoá câu trả lời về shape assistant message.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, mixed>  $options
     * @return array{text: ?string, tool_calls: array<int, array<string, string>>, message: array<string, mixed>, finish_reason: ?string, usage: array<string, mixed>}
     */
    public function chat(array $messages, array $options = []): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('Groq API key is not configured.');
        }

        $payload = [
            'model' => $this->model(),
            'messages' => $this->normalizeMessages($messages),
            'temperature' => $options['temperature'] ?? (float) config('services.groq.temperature', 0.3),
            'max_completion_tokens' => (int) ($options['max_completion_tokens'] ?? config('services.groq.max_completion_tokens', 900)),
        ];
        foreach (['tools', 'tool_choice', 'response_format', 'reasoning_effort', 'parallel_tool_calls', 'top_p'] as $key) {
            if (($options[$key] ?? null) !== null) {
                $payload[$key] = $options[$key];
            }
        }

        if (! isset($payload['reasoning_effort']) && $this->supportsReasoningEffort()) {
            $effort = config('services.groq.reasoning_effort');
            if (filled($effort)) {
                $payload['reasoning_effort'] = $effort;
            }
        }

        $body = $this->send($payload);
        $choice = is_array($body['choices'][0] ?? null) ? $body['choices'][0] : [];
        $message = is_array($choice['message'] ?? null) ? $choice['message'] : [];

        return [
            'text' => is_string($message['content'] ?? null) ? trim($message['content']) : null,
            'tool_calls' => $this->toolCalls($message),
            'message' => $message,
            'finish_reason' => $choice['finish_reason'] ?? null,
            'usage' => is_array($body['usage'] ?? null) ? $body['usage'] : [],
        ];
    }

    /**
     * Một lượt tool calling: nhận input định dạng Responses item và trả về
     * output cũng ở định dạng Responses item.
     *
     * @param  array<int, array<string, mixed>>  $input
     * @param  array<int, array<string, mixed>>  $tools
     * @return array{output: array<int, array<string, mixed>>, usage: array<string, mixed>}
     */
    public function toolTurn(array $input, array $tools, ?int $userId = null, ?string $instructions = null): array
    {
        $messages = filled($instructions) ? [['role' => 'system', 'content' => $instructions]] : [];
        $messages = array_merge($messages, $this->itemsToMessages($input));

        $turn = $this->chat($messages, [
            'tools' => $this->chatTools($tools),
            'tool_choice' => 'auto',
            'max_completion_tokens' => (int) config('services.groq.max_completion_tokens', 900) + 300,
        ]);

        $output = collect($turn['tool_calls'])->map(fn (array $call) => [
            'type' => 'function_call',
            'call_id' => $call['id'],
            'name' => $call['name'],
            'arguments' => $call['arguments'],
        ])->all();

        if (filled($turn['text'])) {
            $output[] = ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => $turn['text']]]];
        }

        return ['output' => $output, 'usage' => $turn['usage']];
    }

    /**
     * Structured output với thang hạ cấp: json_schema strict -> best-effort -> json_object.
     * Model Groq hỗ trợ strict ở các bản mới; model cũ tự rơi về chế độ nhẹ hơn
     * nên chatbot vẫn trả lời được thay vì lỗi 400.
     *
     * @param  array<string, mixed>  $schema
     * @param  string|array<int, array<string, mixed>>  $input
     * @return array<string, mixed>
     */
    public function structured(string $name, array $schema, string $instructions, string|array $input, ?int $userId = null): array
    {
        $messages = array_merge(
            [['role' => 'system', 'content' => $instructions]],
            $this->itemsToMessages(is_array($input) ? $input : [['role' => 'user', 'content' => $input]]),
        );

        $formats = [
            ['type' => 'json_schema', 'json_schema' => ['name' => $name, 'strict' => true, 'schema' => $schema]],
            ['type' => 'json_schema', 'json_schema' => ['name' => $name, 'strict' => false, 'schema' => $schema]],
            ['type' => 'json_object'],
        ];

        $lastError = null;
        foreach ($formats as $index => $format) {
            $attemptMessages = $index === 2
                ? array_merge([$messages[0], ['role' => 'system', 'content' => $this->jsonContract($name, $schema)]], array_slice($messages, 1))
                : $messages;

            try {
                $turn = $this->chat($attemptMessages, ['response_format' => $format, 'temperature' => $index === 2 ? 0.2 : null]);
            } catch (GroqRequestException $exception) {
                if (! in_array($exception->status, [400, 404, 422], true)) {
                    throw $exception;
                }
                $lastError = $exception;

                continue;
            }

            $decoded = $this->decodeJson((string) ($turn['text'] ?? ''));
            if (is_array($decoded)) {
                return $decoded;
            }

            $lastError = new RuntimeException('Groq returned an invalid structured response.');
        }

        throw $lastError ?? new RuntimeException('Groq structured output failed.');
    }

    private function jsonContract(string $name, array $schema): string
    {
        return implode("\n", [
            'OUTPUT CONTRACT:',
            '- Chỉ trả về duy nhất một JSON object hợp lệ cho schema "'.$name.'". Không thêm lời dẫn, không dùng markdown fence.',
            '- JSON schema: '.json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function send(array $payload): array
    {
        try {
            $response = Http::baseUrl(rtrim((string) config('services.groq.base_url', 'https://api.groq.com/openai/v1'), '/'))
                ->withToken((string) config('services.groq.api_key'))
                ->acceptJson()
                ->timeout((int) config('services.groq.timeout', 20))
                ->retry(
                    (int) config('services.groq.attempts', 3),
                    fn (int $attempt) => 250 * (2 ** ($attempt - 1)),
                    fn (\Throwable $exception, PendingRequest $request) => $this->shouldRetry($exception),
                    throw: false,
                )
                ->post('/chat/completions', $payload);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Cannot connect to Groq.', previous: $exception);
        }

        if (! $response->successful()) {
            throw new GroqRequestException($this->errorMessage($response->status(), $response->json()), $response->status());
        }

        $body = $response->json();

        return is_array($body) ? $body : throw new RuntimeException('Groq returned an unreadable response.');
    }

    private function errorMessage(int $status, mixed $body): string
    {
        $detail = is_array($body) ? trim((string) data_get($body, 'error.message', '')) : '';

        if ($status === 429) {
            $code = is_array($body) ? (string) data_get($body, 'error.code', 'rate_limit_exceeded') : 'rate_limit_exceeded';

            return in_array($code, ['insufficient_quota', 'billing_hard_limit_reached'], true)
                ? 'Groq usage or billing quota has been exhausted (quota).'
                : 'Groq rate limit was exceeded (429 rate limit).';
        }

        return trim('Groq request failed with HTTP '.$status.'. '.$detail);
    }

    private function shouldRetry(\Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        if (! $exception instanceof RequestException || ! $exception->response) {
            return false;
        }

        $status = $exception->response->status();
        if ($status === 429) {
            // Hạn mức token/phút của Groq chỉ hồi phục sau vài chục giây nên
            // retry với backoff ngắn là vô ích, chỉ làm khách chờ lâu.
            // Thất bại nhanh để AiChatbotService hạ cấp sang engine luật.
            return false;
        }

        return in_array($status, [408, 409], true) || $status >= 500;
    }

    /**
     * Chuyển tool definition kiểu Responses API sang tools của chat completions.
     *
     * @param  array<int, array<string, mixed>>  $tools
     * @return array<int, array<string, mixed>>
     */
    private function chatTools(array $tools): array
    {
        return collect($tools)->map(function (array $tool): array {
            $function = is_array($tool['function'] ?? null) ? $tool['function'] : $tool;

            return [
                'type' => 'function',
                'function' => [
                    'name' => (string) ($function['name'] ?? ''),
                    'description' => (string) ($function['description'] ?? ''),
                    'parameters' => $this->jsonSchemaParameters($function['parameters'] ?? null),
                ],
            ];
        })->filter(fn (array $tool) => $tool['function']['name'] !== '')->values()->all();
    }

    /**
     * Chuẩn hoá parameters thành JSON schema mà Groq chấp nhận.
     *
     * Groq kiểm tra schema khá nghiêm: `properties` bắt buộc là JSON object.
     * PHP json_encode mảng rỗng thành `[]`, nên tool không có tham số (ví dụ
     * get_promotions) từng bị HTTP 400 "at '/properties': got array, want object".
     *
     * @return array<string, mixed>
     */
    private function jsonSchemaParameters(mixed $parameters): array
    {
        $parameters = is_array($parameters) ? $parameters : [];
        $properties = $parameters['properties'] ?? [];
        if (! is_array($properties)) {
            $properties = [];
        }

        $parameters['type'] = 'object';
        $parameters['properties'] = $properties === [] ? new \stdClass() : $properties;
        $parameters['required'] ??= array_keys($properties);
        $parameters['additionalProperties'] ??= false;

        return $parameters;
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array<int, array<string, string>>
     */
    private function toolCalls(array $message): array
    {
        return collect($message['tool_calls'] ?? [])->map(function ($call): array {
            $arguments = data_get($call, 'function.arguments');

            return [
                'id' => (string) ($call['id'] ?? ''),
                'name' => (string) data_get($call, 'function.name', ''),
                'arguments' => is_string($arguments) && $arguments !== ''
                    ? $arguments
                    : json_encode(is_array($arguments) ? $arguments : [], JSON_UNESCAPED_UNICODE),
            ];
        })->filter(fn (array $call) => $call['name'] !== '' && $call['id'] !== '')->values()->all();
    }

    /**
     * Chuyển chuỗi item của Responses API sang chat messages của Groq.
     * Các function_call liên tiếp được gộp vào một assistant message để mọi
     * tool result đều đứng ngay sau lượt gọi tool tương ứng.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function itemsToMessages(array $items): array
    {
        $messages = [];
        $pendingCalls = [];
        $flushCalls = function () use (&$messages, &$pendingCalls): void {
            if ($pendingCalls !== []) {
                $messages[] = ['role' => 'assistant', 'content' => '', 'tool_calls' => $pendingCalls];
                $pendingCalls = [];
            }
        };

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (($item['type'] ?? null) === 'function_call') {
                $pendingCalls[] = [
                    'id' => (string) ($item['call_id'] ?? ''),
                    'type' => 'function',
                    'function' => [
                        'name' => (string) ($item['name'] ?? ''),
                        'arguments' => (string) ($item['arguments'] ?? '{}'),
                    ],
                ];

                continue;
            }

            $flushCalls();

            if (($item['type'] ?? null) === 'function_call_output') {
                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => (string) ($item['call_id'] ?? ''),
                    'content' => $this->stringify($item['output'] ?? ''),
                ];

                continue;
            }

            $role = (string) ($item['role'] ?? 'user');
            $messages[] = [
                'role' => in_array($role, ['assistant', 'system'], true) ? $role : 'user',
                'content' => $this->stringify($item['content'] ?? ''),
            ];
        }

        $flushCalls();

        return $messages;
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @return array<int, array<string, mixed>>
     */
    private function normalizeMessages(array $messages): array
    {
        return collect($messages)->map(function ($message): array {
            if (! is_array($message)) {
                return ['role' => 'user', 'content' => $this->stringify($message)];
            }

            $role = in_array($message['role'] ?? null, ['system', 'assistant', 'tool'], true) ? $message['role'] : 'user';

            if ($role === 'tool') {
                return [
                    'role' => 'tool',
                    'tool_call_id' => (string) ($message['tool_call_id'] ?? ''),
                    'content' => $this->stringify($message['content'] ?? ''),
                ];
            }

            if ($role === 'assistant' && filled($message['tool_calls'] ?? null)) {
                return ['role' => 'assistant', 'content' => (string) ($message['content'] ?? ''), 'tool_calls' => $message['tool_calls']];
            }

            return ['role' => $role, 'content' => $this->stringify($message['content'] ?? '')];
        })->values()->all();
    }

    private function stringify(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_array($value)) {
            $text = collect($value)->where('type', 'output_text')->pluck('text')->filter()->join("\n");

            return $text !== '' ? $text : (json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
        }

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJson(string $text): ?array
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        if (str_starts_with($text, '```')) {
            $text = trim(preg_replace('/^```(?:json)?|```$/m', '', $text) ?? $text);
        }

        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);

        return is_array($decoded) ? $decoded : null;
    }
}
