<?php

namespace App\Http\Controllers;

use App\Models\ChatbotKnowledge;
use App\Services\KnowledgeBaseIndexerService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminKnowledgeBaseController extends Controller
{
    public function index(Request $request)
    {
        $items = ChatbotKnowledge::query()->withCount('documents')->with('editor:id,name')
            ->when($request->filled('search'), fn ($query) => $query->where(fn ($nested) => $nested
                ->where('title', 'like', '%'.$request->search.'%')
                ->orWhere('answer', 'like', '%'.$request->search.'%')))
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->category))
            ->when($request->filled('sync_status'), fn ($query) => $query->where('sync_status', $request->sync_status))
            ->orderByDesc('priority')->latest()->paginate(15)->withQueryString();

        return view('admin.knowledge-base.index', [
            'items' => $items,
            'categories' => $this->categories(),
            'stats' => [
                'total' => ChatbotKnowledge::count(),
                'active' => ChatbotKnowledge::where('active', true)->count(),
                'synced' => ChatbotKnowledge::where('sync_status', 'SYNCED')->count(),
                'failed' => ChatbotKnowledge::where('sync_status', 'FAILED')->count(),
            ],
        ]);
    }

    public function create()
    {
        return view('admin.knowledge-base.form', ['knowledge' => new ChatbotKnowledge, 'categories' => $this->categories()]);
    }

    public function store(Request $request, KnowledgeBaseIndexerService $indexer)
    {
        $knowledge = ChatbotKnowledge::create($this->validated($request) + [
            'intent' => 'KB_'.Str::upper(Str::random(16)),
            'keywords' => $this->keywords($request),
            'updated_by' => $request->user()->id,
            'sync_status' => 'PENDING',
        ]);

        return $this->syncAndRedirect($knowledge, $indexer, 'Đã thêm kiến thức và cập nhật RAG.');
    }

    public function edit(ChatbotKnowledge $knowledge)
    {
        return view('admin.knowledge-base.form', compact('knowledge') + ['categories' => $this->categories()]);
    }

    public function update(Request $request, ChatbotKnowledge $knowledge, KnowledgeBaseIndexerService $indexer)
    {
        $knowledge->update($this->validated($request) + [
            'keywords' => $this->keywords($request),
            'updated_by' => $request->user()->id,
            'sync_status' => 'PENDING',
            'sync_error' => null,
        ]);

        return $this->syncAndRedirect($knowledge, $indexer, 'Đã cập nhật kiến thức và embedding mới.');
    }

    public function sync(ChatbotKnowledge $knowledge, KnowledgeBaseIndexerService $indexer)
    {
        return $this->syncAndRedirect($knowledge, $indexer, 'Đã tạo lại chunk và embedding.');
    }

    public function toggle(Request $request, ChatbotKnowledge $knowledge, KnowledgeBaseIndexerService $indexer)
    {
        $knowledge->update(['active' => ! $knowledge->active, 'updated_by' => $request->user()->id, 'sync_status' => 'PENDING']);

        return $this->syncAndRedirect($knowledge, $indexer, $knowledge->active ? 'Đã kích hoạt kiến thức.' : 'Đã tắt kiến thức khỏi RAG.');
    }

    public function destroy(ChatbotKnowledge $knowledge, KnowledgeBaseIndexerService $indexer)
    {
        $indexer->remove($knowledge);

        return redirect()->route('admin.knowledge-base.index')->with('success', 'Đã xóa kiến thức và các vector liên quan.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(array_keys($this->categories()))],
            'answer' => ['required', 'string', 'max:30000'],
            'priority' => ['required', 'integer', 'between:0,100'],
            'active' => ['nullable', 'boolean'],
            'keywords_text' => ['nullable', 'string', 'max:1000'],
        ]) + ['active' => $request->boolean('active')];
    }

    private function keywords(Request $request): array
    {
        return collect(preg_split('/[,\n]+/u', (string) $request->keywords_text))
            ->map(fn ($keyword) => trim($keyword))->filter()->unique()->take(30)->values()->all();
    }

    private function syncAndRedirect(ChatbotKnowledge $knowledge, KnowledgeBaseIndexerService $indexer, string $message)
    {
        try {
            $chunks = $indexer->sync($knowledge->fresh());

            return redirect()->route('admin.knowledge-base.index')->with('success', $message." ({$chunks} chunk)");
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route('admin.knowledge-base.index')->with('error', 'Đã lưu nội dung nhưng chưa tạo được embedding. Bạn có thể bấm Đồng bộ lại.');
        }
    }

    private function categories(): array
    {
        return [
            'BOOKING_GUIDE' => 'Hướng dẫn đặt sân',
            'CANCELLATION' => 'Chính sách hủy sân',
            'PAYMENT' => 'Quy định thanh toán',
            'REFUND' => 'Chính sách hoàn tiền',
            'SERVICE' => 'Dịch vụ',
            'PROMOTION' => 'Khuyến mãi',
            'ACCOUNT' => 'Tài khoản',
            'FAQ' => 'FAQ',
            'OTHER' => 'Khác',
        ];
    }
}
