<?php

namespace Tests\Feature;

use App\Models\News;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_article_is_readable_at_publication_time_but_not_before(): void
    {
        $this->freezeTime();
        $article = News::create(['title'=>'Publish now', 'content'=>'Visible article', 'status'=>'PUBLISHED', 'published_at'=>now()]);
        $this->get(route('news.show', $article))->assertOk()->assertSee('Visible article');
        $article->update(['published_at'=>now()->addMinute()]);
        $this->get(route('news.show', $article))->assertNotFound();
        $article->update(['status'=>'DRAFT', 'published_at'=>now()->subDay()]);
        $this->get(route('news.show', $article))->assertNotFound();
    }

    public function test_public_news_page_shows_published_articles_and_opens_the_detail(): void
    {
        $published = News::create([
            'title' => 'Hướng dẫn chọn sân', 'slug' => 'huong-dan-chon-san', 'content' => 'Nội dung bài viết.',
            'status' => 'PUBLISHED', 'published_at' => now()->subMinute(),
        ]);
        News::create([
            'title' => 'Bản nháp nội bộ', 'slug' => 'ban-nhap-noi-bo', 'content' => 'Không công khai.',
            'status' => 'DRAFT', 'published_at' => now()->subMinute(),
        ]);

        $this->get(route('news.index'))
            ->assertOk()
            ->assertSee($published->title)
            ->assertDontSee('Bản nháp nội bộ');

        $this->get(route('news.show', $published))
            ->assertOk()
            ->assertSee('Nội dung bài viết.');
    }
}
