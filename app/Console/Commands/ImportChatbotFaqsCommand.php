<?php

namespace App\Console\Commands;

use App\Models\ChatbotFaq;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class ImportChatbotFaqsCommand extends Command
{
    protected $signature = 'chatbot:import-faqs {file : Đường dẫn file SQL FAQ}';
    protected $description = 'Import các tuple FAQ từ câu lệnh INSERT INTO chatbot_faqs';

    public function handle(): int
    {
        $path = $this->argument('file');
        if (! is_file($path) || ! is_readable($path)) {
            $this->error('Không đọc được file FAQ: '.$path);
            return self::FAILURE;
        }

        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new RuntimeException('Không thể đọc nội dung file FAQ.');
        }

        $pattern = "/\(\s*'((?:''|[^'])*)'\s*,\s*'((?:''|[^'])*)'\s*,\s*'((?:''|[^'])*)'\s*,\s*'((?:''|[^'])*)'\s*\)/su";
        preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER);

        if ($matches === []) {
            $this->error('Không tìm thấy bản ghi FAQ hợp lệ trong file.');
            return self::FAILURE;
        }

        $count = 0;
        foreach ($matches as $match) {
            [, $category, $question, $answer, $keywords] = array_map(fn ($value) => str_replace("''", "'", trim($value)), $match);
            $keywordList = collect(explode(',', $keywords))->map(fn ($keyword) => trim($keyword))->filter()->unique()->values()->all();

            ChatbotFaq::updateOrCreate(
                ['question_hash' => hash('sha256', Str::lower(Str::ascii($question)))],
                ['category' => $category, 'question' => $question, 'answer' => $answer, 'keywords' => $keywordList, 'priority' => 5, 'active' => true]
            );
            $count++;
        }

        Cache::forget('chatbot.faqs.active');

        $this->info("Đã import {$count} FAQ vào chatbot_faqs.");
        return self::SUCCESS;
    }
}
