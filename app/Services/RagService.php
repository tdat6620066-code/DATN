<?php

namespace App\Services;

use App\Models\ChatbotDocument;
use Illuminate\Support\Facades\Cache;

class RagService
{
    public function __construct(private readonly OpenAiService $openai) {}

    public function search(string $query, int $limit = 5): array
    {
        if (! $this->openai->configured() || blank($query)) {
            return [];
        }

        $indexVersion = (string) (ChatbotDocument::query()->max('updated_at') ?? '0');
        $cacheKey = 'chatbot.rag.'.sha1($query.'|'.$limit.'|'.$indexVersion.'|'.config('services.openai.rag_threshold'));

        return Cache::remember($cacheKey, now()->addMinutes((int) config('chatbot.rag_cache_minutes', 15)), function () use ($query, $limit) {
            try {
                $queryVector = $this->openai->embeddings($query)[0] ?? [];
            } catch (\Throwable $exception) {
                report($exception);

                return [];
            }

            if ($queryVector === []) {
                return [];
            }

            return ChatbotDocument::query()->where('active', true)->whereNotNull('embedding')->get()
                ->map(fn (ChatbotDocument $document) => [
                    'id' => $document->id,
                    'source_type' => $document->source_type,
                    'source_id' => $document->source_id,
                    'title' => $document->title,
                    'content' => $document->content,
                    'metadata' => $document->metadata,
                    'score' => $this->cosine($queryVector, $document->embedding ?? []),
                ])
                ->filter(fn (array $item) => $item['score'] >= (float) config('services.openai.rag_threshold', 0.68))
                ->sortByDesc('score')->take($limit)->values()->all();
        });
    }

    private function cosine(array $left, array $right): float
    {
        if (count($left) !== count($right) || $left === []) {
            return 0;
        }
        $dot = $leftNorm = $rightNorm = 0.0;
        foreach ($left as $index => $value) {
            $dot += $value * $right[$index];
            $leftNorm += $value ** 2;
            $rightNorm += $right[$index] ** 2;
        }

        return $leftNorm > 0 && $rightNorm > 0 ? $dot / (sqrt($leftNorm) * sqrt($rightNorm)) : 0;
    }
}
