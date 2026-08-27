<?php

namespace App\Services;

use App\Models\ChatbotDocument;
use App\Models\ChatbotKnowledge;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class KnowledgeBaseIndexerService
{
    public function __construct(private readonly OpenAiService $openai) {}

    public function sync(ChatbotKnowledge $knowledge): int
    {
        Cache::forget('chatbot.knowledge.active');
        if (! $knowledge->active) {
            ChatbotDocument::query()->where('source_type', 'knowledge')->where('source_id', $knowledge->id)->update(['active' => false]);
            $knowledge->update(['sync_status' => 'SYNCED', 'sync_error' => null, 'synced_at' => now()]);

            return 0;
        }
        if (! $this->openai->configured()) {
            $this->failed($knowledge, 'Chưa cấu hình OpenAI API key để tạo embedding.');
            throw new RuntimeException('OpenAI API key is not configured.');
        }

        $chunks = $this->chunks(trim(($knowledge->title ?: $knowledge->intent)."\n\n".$knowledge->answer));
        try {
            $vectors = $this->openai->embeddings($chunks);
            if (count($vectors) !== count($chunks)) {
                throw new RuntimeException('Số embedding trả về không khớp số chunk.');
            }
            DB::transaction(function () use ($knowledge, $chunks, $vectors) {
                ChatbotDocument::query()->where('source_type', 'knowledge')->where('source_id', $knowledge->id)->delete();
                foreach ($chunks as $index => $content) {
                    ChatbotDocument::create([
                        'source_type' => 'knowledge',
                        'source_id' => $knowledge->id,
                        'chunk_index' => $index,
                        'title' => $knowledge->title ?: $knowledge->intent,
                        'content' => $content,
                        'metadata' => [
                            'category' => $knowledge->category,
                            'answer' => $content,
                            'knowledge_id' => $knowledge->id,
                        ],
                        'embedding' => $vectors[$index],
                        'content_hash' => hash('sha256', $content),
                        'active' => true,
                    ]);
                }
                $knowledge->update(['sync_status' => 'SYNCED', 'sync_error' => null, 'synced_at' => now()]);
            });

            return count($chunks);
        } catch (\Throwable $exception) {
            $this->failed($knowledge, $exception->getMessage());
            throw $exception;
        }
    }

    public function remove(ChatbotKnowledge $knowledge): void
    {
        DB::transaction(function () use ($knowledge) {
            ChatbotDocument::query()->where('source_type', 'knowledge')->where('source_id', $knowledge->id)->delete();
            $knowledge->delete();
        });
        Cache::forget('chatbot.knowledge.active');
    }

    public function chunks(string $content, int $maxCharacters = 1000, int $overlap = 150): array
    {
        $paragraphs = preg_split('/\n{2,}/u', $content, -1, PREG_SPLIT_NO_EMPTY) ?: [$content];
        $chunks = [];
        $current = '';
        foreach ($paragraphs as $paragraph) {
            foreach (mb_str_split(trim($paragraph), $maxCharacters) as $piece) {
                $candidate = trim($current."\n\n".$piece);
                if ($current !== '' && mb_strlen($candidate) > $maxCharacters) {
                    $chunks[] = $current;
                    $current = trim(mb_substr($current, -$overlap)."\n\n".$piece);
                } else {
                    $current = $candidate;
                }
            }
        }
        if ($current !== '') {
            $chunks[] = $current;
        }

        return array_values(array_filter($chunks));
    }

    private function failed(ChatbotKnowledge $knowledge, string $message): void
    {
        $knowledge->update(['sync_status' => 'FAILED', 'sync_error' => mb_substr($message, 0, 2000)]);
    }
}
