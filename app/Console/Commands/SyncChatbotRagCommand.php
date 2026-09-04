<?php

namespace App\Console\Commands;

use App\Models\ChatbotDocument;
use App\Models\ChatbotFaq;
use App\Models\ChatbotKnowledge;
use App\Models\Court;
use App\Models\Promotion;
use App\Models\ServiceItem;
use App\Services\OpenAiService;
use Illuminate\Console\Command;

class SyncChatbotRagCommand extends Command
{
    protected $signature = 'chatbot:sync-rag {--force : Tạo lại embedding dù nội dung không đổi}';

    protected $description = 'Đồng bộ dữ liệu SmashZone vào kho semantic của chatbot';

    public function handle(OpenAiService $openai): int
    {
        if (! $openai->configured()) {
            $this->error('Chưa cấu hình OPENAI_API_KEY.');

            return self::FAILURE;
        }

        $documents = collect()
            ->merge(ChatbotFaq::where('active', true)->get()->map(fn ($item) => ['faq', $item->id, $item->question, $item->question."\n".$item->answer, ['category' => $item->category, 'answer' => $item->answer]]))
            ->merge(ChatbotKnowledge::where('active', true)->get()->map(fn ($item) => ['knowledge', $item->id, $item->intent, $item->intent."\n".$item->answer, ['answer' => $item->answer]]))
            ->merge(Court::where('status', 'ACTIVE')->with('courtType')->get()->map(fn ($item) => ['court', $item->id, $item->name, implode("\n", array_filter([$item->name, $item->courtType?->name, $item->address, $item->description])), ['court_id' => $item->id]]))
            ->merge(ServiceItem::where('is_active', true)->get()->map(fn ($item) => ['service', $item->id, $item->name, $item->name."\n".$item->category."\nGiá: ".$item->price, ['service_id' => $item->id]]))
            ->merge(Promotion::where('status', 'ACTIVE')->get()->map(fn ($item) => ['promotion', $item->id, $item->title, $item->title."\n".$item->description, ['promotion_id' => $item->id]]));

        $pending = collect();
        foreach ($documents as [$type, $id, $title, $content, $metadata]) {
            $hash = hash('sha256', $content);
            $document = ChatbotDocument::firstOrNew(['source_type' => $type, 'source_id' => $id]);
            if ($this->option('force') || $document->content_hash !== $hash || blank($document->embedding)) {
                $document->fill(compact('title', 'content', 'metadata') + ['content_hash' => $hash, 'active' => true])->save();
                $pending->push($document);
            }
        }

        foreach ($pending->chunk(100) as $chunk) {
            $vectors = $openai->embeddings($chunk->pluck('content')->all());
            foreach ($chunk->values() as $index => $document) {
                $document->update(['embedding' => $vectors[$index] ?? null]);
            }
        }

        $this->info("Đã đồng bộ {$documents->count()} tài liệu; tạo lại {$pending->count()} embedding.");

        return self::SUCCESS;
    }
}
