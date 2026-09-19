<?php

namespace App\Http\Controllers;

use App\Models\{Banner, Brand, ChatbotFaq, News, Promotion, Review, ServiceItem};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminContentController extends Controller
{
    public const TYPES = [
        'brands' => [Brand::class, 'Thương hiệu', 'name', ['name' => 'Tên thương hiệu', 'description' => 'Mô tả', 'website' => 'Website', 'image' => 'Ảnh', 'status' => 'Trạng thái']],
        'services' => [ServiceItem::class, 'Dịch vụ & tồn kho', 'name', ['code' => 'Mã dịch vụ', 'name' => 'Tên dịch vụ', 'category' => 'Loại dịch vụ', 'price' => 'Giá (VNĐ)', 'brand_id' => 'Thương hiệu', 'is_active' => 'Hoạt động']],
        'banners' => [Banner::class, 'Slider', 'title', ['title' => 'Tiêu đề', 'image' => 'Ảnh', 'link' => 'Liên kết', 'start_at' => 'Bắt đầu', 'end_at' => 'Kết thúc', 'sort_order' => 'Thứ tự', 'status' => 'Trạng thái']],
        'news' => [News::class, 'Tin tức', 'title', ['title' => 'Tiêu đề', 'thumbnail' => 'Ảnh', 'content' => 'Nội dung', 'published_at' => 'Ngày xuất bản', 'status' => 'Trạng thái']],
        'promotions' => [Promotion::class, 'Chương trình khuyến mãi', 'title', ['title' => 'Tiêu đề', 'description' => 'Mô tả', 'image' => 'Ảnh', 'start_at' => 'Bắt đầu', 'end_at' => 'Kết thúc', 'status' => 'Trạng thái']],
        'faqs' => [ChatbotFaq::class, 'Câu hỏi thường gặp', 'question', ['category' => 'Chủ đề', 'question' => 'Câu hỏi', 'answer' => 'Trả lời', 'priority' => 'Ưu tiên', 'active' => 'Hoạt động']],
        'reviews' => [Review::class, 'Bình luận', 'content', ['content' => 'Nội dung', 'status' => 'Trạng thái']],
    ];

    private function definition(string $kind): array
    {
        abort_unless(isset(self::TYPES[$kind]), 404);
        return self::TYPES[$kind];
    }

    public function index(Request $request, string $kind)
    {
        [$class, $title, $label, $fields] = $this->definition($kind);
        $query = $class::query();
        if ($request->filled('search')) $query->where($label, 'like', '%'.$request->string('search').'%');
        $statusField = $kind === 'services' ? 'is_active' : ($kind === 'faqs' ? 'active' : 'status');
        if ($request->filled('status')) $query->where($statusField, $request->input('status'));
        if ($kind === 'services' && $request->boolean('low_stock')) $query->whereNotNull('stock')->where('stock', '<=', 5);
        $items = $query->latest()->paginate(15)->withQueryString();
        return view('admin.content.index', compact('kind', 'title', 'label', 'items', 'statusField') + ['statuses' => $this->statuses($kind)]);
    }

    public function create(string $kind)
    {
        abort_if($kind === 'reviews', 404);
        [$class] = $this->definition($kind);
        return $this->form($kind, new $class);
    }

    public function edit(string $kind, int $id) { [$class] = $this->definition($kind); return $this->form($kind, $class::findOrFail($id)); }

    private function form(string $kind, $item, bool $readOnly = false)
    {
        [, $title, $label, $fields] = $this->definition($kind);
        return view('admin.content.form', compact('kind', 'title', 'label', 'fields', 'item', 'readOnly') + [
            'statuses' => $this->statuses($kind), 'brands' => Brand::orderBy('name')->get(),
            'movements' => $kind === 'services' && $item->exists ? DB::table('stock_movements')->where('service_item_id', $item->id)->latest('id')->limit(20)->get() : collect(),
        ]);
    }

    public function show(string $kind, int $id) { [$class] = $this->definition($kind); return $this->form($kind, $class::findOrFail($id), true); }

    public function store(Request $request, string $kind)
    {
        abort_if($kind === 'reviews', 404);
        [$class] = $this->definition($kind);
        $item = new $class;
        $this->save($request, $kind, $item);
        return redirect()->route('admin.content.edit', [$kind, $item->id])->with('success', 'Đã thêm mới.');
    }

    public function update(Request $request, string $kind, int $id)
    {
        [$class] = $this->definition($kind);
        $this->save($request, $kind, $class::findOrFail($id));
        return back()->with('success', 'Đã lưu thay đổi.');
    }

    private function save(Request $request, string $kind, $item): void
    {
        [, , , $fields] = $this->definition($kind);
        $rules = [];
        foreach ($fields as $field => $label) {
            $rules[$field] = match ($field) {
                'name', 'title', 'question' => ['required', 'string', 'max:255'],
                'content', 'answer' => ['required', 'string', 'max:30000'],
                'description' => ['nullable', 'string', 'max:10000'],
                'image', 'thumbnail' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
                'link', 'website' => ['nullable', 'url:http,https', 'max:255'],
                'price' => ['required', 'numeric', 'min:0', 'max:999999999'],
                'code' => ['required', 'string', 'max:50', Rule::unique('service_items', 'code')->ignore($item->id)],
                'brand_id' => ['nullable', 'exists:brands,id'],
                'is_active', 'active' => ['required', 'boolean'],
                'priority', 'sort_order' => ['required', 'integer', 'min:0', 'max:10000'],
                'status' => ['required', Rule::in(array_keys($this->statuses($kind)))],
                'start_at', 'published_at' => ['nullable', 'date'],
                'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
                default => ['nullable', 'string', 'max:255'],
            };
        }
        if ($kind === 'brands') $rules['name'][] = Rule::unique('brands', 'name')->ignore($item->id);
        if ($kind === 'faqs') {
            $rules['category'] = ['required', 'string', 'max:60'];
            $rules['question'][] = Rule::unique('chatbot_faqs', 'question')->ignore($item->id);
        }
        $data = $request->validate($rules);
        foreach (['image', 'thumbnail'] as $field) {
            if ($request->hasFile($field)) $data[$field] = $request->file($field)->store('content/'.$kind, 'public');
            else unset($data[$field]);
        }
        if ($kind === 'news' && ! $item->exists) $data['slug'] = Str::slug($data['title']).'-'.Str::lower(Str::random(8));
        if ($kind === 'faqs') {
            $data['question_hash'] = hash('sha256', mb_strtolower(trim($data['question'])));
            if (ChatbotFaq::where('question_hash',$data['question_hash'])->when($item->exists,fn($q)=>$q->where('id','!=',$item->id))->exists()) throw \Illuminate\Validation\ValidationException::withMessages(['question'=>'Câu hỏi đã tồn tại.']);
            $data['keywords'] = $item->keywords ?? [];
        }
        if ($kind === 'services' && ! $item->exists) $data['stock'] = 0;
        $item->fill($data)->save();
    }

    public function destroy(string $kind, int $id)
    {
        [$class] = $this->definition($kind);
        $item = $class::findOrFail($id);
        if ($kind === 'brands' && ServiceItem::where('brand_id', $id)->exists()) return back()->with('error', 'Thương hiệu đang được dùng. Hãy ngừng hoạt động hoặc chuyển dịch vụ sang thương hiệu khác.');
        if ($kind === 'services' && (DB::table('booking_services')->where('service_item_id', $id)->exists() || DB::table('stock_movements')->where('service_item_id', $id)->exists())) {
            $item->update(['is_active' => false]);
            return back()->with('success', 'Dịch vụ đã phát sinh dữ liệu được chuyển sang ngừng hoạt động.');
        }
        $item->delete();
        return redirect()->route('admin.content.index', $kind)->with('success', 'Đã xóa.');
    }

    public function stock(Request $request, ServiceItem $serviceItem)
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'not_in:0', 'between:-1000000,1000000'], 'reason' => ['required', 'string', 'max:1000']]);
        DB::transaction(function () use ($serviceItem, $data, $request) {
            $item = ServiceItem::lockForUpdate()->findOrFail($serviceItem->id);
            $balance = ($item->stock ?? 0) + $data['quantity'];
            if ($balance < 0) throw \Illuminate\Validation\ValidationException::withMessages(['quantity' => 'Số lượng xuất vượt tồn kho.']);
            $item->update(['stock' => $balance]);
            DB::table('stock_movements')->insert(['service_item_id' => $item->id, 'actor_id' => $request->user()->id, 'quantity' => $data['quantity'], 'balance' => $balance, 'reason' => $data['reason'], 'created_at' => now(), 'updated_at' => now()]);
        });
        return back()->with('success', 'Đã cập nhật tồn kho và lưu lịch sử.');
    }

    private function statuses(string $kind): array
    {
        return match ($kind) {
            'news' => ['DRAFT' => 'Bản nháp', 'PUBLISHED' => 'Xuất bản', 'ARCHIVED' => 'Lưu trữ'],
            'reviews' => ['PENDING' => 'Chờ duyệt', 'APPROVED' => 'Hiển thị', 'REJECTED' => 'Ẩn'],
            'services', 'faqs' => [1 => 'Hoạt động', 0 => 'Ngừng hoạt động'],
            default => ['ACTIVE' => 'Hoạt động', 'INACTIVE' => 'Ngừng hoạt động'],
        };
    }
}
