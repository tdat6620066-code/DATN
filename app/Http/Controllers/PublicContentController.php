<?php
namespace App\Http\Controllers;
use App\Models\{Brand, ChatbotFaq, News, SystemSetting};
class PublicContentController extends Controller
{
    public function index()
    {
        return view('information', ['brands'=>Brand::where('status','ACTIVE')->orderBy('name')->get(), 'faqs'=>ChatbotFaq::where('active',true)->orderByDesc('priority')->get(), 'settings'=>SystemSetting::pluck('value','key'), 'news'=>News::where('status','PUBLISHED')->where(fn($q)=>$q->whereNull('published_at')->orWhere('published_at','<=',now()))->latest('published_at')->paginate(10)]);
    }
    public function news(News $news)
    {
        abort_unless($news->status==='PUBLISHED' && (!$news->published_at || $news->published_at<=now()),404);
        return view('news-show',compact('news'));
    }
}
