<?php

namespace App\Http\Controllers;

use App\Models\News;

class NewsController extends Controller
{
    public function index()
    {
        $news = $this->published()->paginate(12);

        return view('news.index', compact('news'));
    }

    public function show(News $news)
    {
        abort_unless($this->isPublished($news), 404);

        $relatedNews = $this->published()
            ->whereKeyNot($news->id)
            ->limit(3)
            ->get();

        return view('news.show', compact('news', 'relatedNews'));
    }

    private function published()
    {
        return News::query()
            ->where('status', 'PUBLISHED')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->latest('published_at');
    }

    private function isPublished(News $news): bool
    {
        return $news->status === 'PUBLISHED'
            && $news->published_at !== null
            && $news->published_at->isPast();
    }
}
