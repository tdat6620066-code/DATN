<?php

namespace App\Http\Controllers;

use App\Models\Amenity;
use App\Models\Court;
use App\Models\CourtType;
use App\Models\TimeSlot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminCourtController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAdmin($request);
        $courts = Court::with(['courtType', 'images'])->withCount('bookingDetails')->when($request->filled('search'), fn ($q) => $q->where(fn ($i) => $i->where('name', 'like', '%'.$request->search.'%')->orWhere('code', 'like', '%'.$request->search.'%')))->latest()->paginate(15)->withQueryString();

        return view('admin.courts.index', compact('courts'));
    }

    public function create(Request $request)
    {
        $this->authorizeAdmin($request);

        return view('admin.courts.form', $this->formData());
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin($request);
        $data = $this->validateCourt($request);
        $court = DB::transaction(function () use ($request, $data) {
            $court = Court::create($this->attributes($data) + ['code' => $this->uniqueCode($data['name'])]);
            $this->sync($court, $data);
            $this->storeImages($court, $request);

            return $court;
        });

        return redirect()->route('admin.courts.edit', $court)->with('success', 'Đã thêm sân mới.');
    }

    public function edit(Court $court, Request $request)
    {
        $this->authorizeAdmin($request);
        $court->load(['amenities', 'images', 'prices']);

        return view('admin.courts.form', $this->formData($court));
    }

    public function update(Court $court, Request $request)
    {
        $this->authorizeAdmin($request);
        $data = $this->validateCourt($request, $court);
        DB::transaction(function () use ($court, $request, $data) {
            $court->update($this->attributes($data));
            $this->sync($court, $data);
            $this->removeImages($court, $data['remove_image_ids'] ?? []);
            $this->storeImages($court, $request);
        });

        return back()->with('success', 'Đã cập nhật thông tin sân.');
    }

    public function destroy(Court $court, Request $request)
    {
        $this->authorizeAdmin($request);
        if ($court->bookingDetails()->exists()) {
            $court->update(['status' => 'INACTIVE']);

            return back()->with('error', 'Sân đã phát sinh booking nên không thể xóa cứng. Hệ thống đã chuyển sân sang ngừng hoạt động.');
        } foreach ($court->images as $image) {
            Storage::disk('public')->delete($image->image);
        } $court->delete();

        return redirect()->route('admin.courts.index')->with('success', 'Đã xóa sân.');
    }

    private function validateCourt(Request $request, ?Court $court = null): array
    {
        $data = $request->validate(
            ['name' => ['required', 'string', 'max:255', Rule::unique('courts', 'name')->ignore($court)], 'court_type_id' => ['required', 'exists:court_types,id'], 'description' => ['nullable', 'string', 'max:5000'], 'address' => ['nullable', 'string', 'max:500'], 'phone' => ['nullable', 'string', 'max:30'], 'opening_time' => ['required', 'date_format:H:i'], 'closing_time' => ['required', 'date_format:H:i', 'after:opening_time'], 'status' => ['required', Rule::in(['ACTIVE', 'INACTIVE'])], 'is_featured' => ['nullable', 'boolean'], 'amenity_ids' => ['nullable', 'array'], 'amenity_ids.*' => ['integer', 'exists:amenities,id'], 'default_price' => ['required', 'numeric', 'min:0', 'max:999999999'], 'images' => [$court ? 'nullable' : 'required', 'array', 'min:1', 'max:8'], 'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'], 'remove_image_ids' => ['nullable', 'array'], 'remove_image_ids.*' => ['integer']],
            ['images.required' => 'Vui lòng chọn ít nhất một hình ảnh cho sân.', 'images.min' => 'Vui lòng chọn ít nhất một hình ảnh cho sân.', 'images.*.image' => 'Tệp tải lên phải là hình ảnh.', 'images.*.mimes' => 'Ảnh phải có định dạng JPG, PNG hoặc WEBP.', 'images.*.max' => 'Mỗi ảnh không được vượt quá 5MB.']
        );

        $data['prices'] = TimeSlot::where('status', 'ACTIVE')
            ->where('start_time', '>=', $data['opening_time'])
            ->where('end_time', '<=', $data['closing_time'])
            ->pluck('id')
            ->mapWithKeys(fn ($id) => [$id => $data['default_price']])
            ->all();

        return $data;
    }

    private function attributes(array $data): array
    {
        return ['name' => $data['name'], 'court_type_id' => $data['court_type_id'], 'description' => $data['description'] ?? null, 'address' => $data['address'] ?? null, 'phone' => $data['phone'] ?? null, 'opening_time' => $data['opening_time'], 'closing_time' => $data['closing_time'], 'status' => $data['status'], 'is_featured' => (bool) ($data['is_featured'] ?? false)];
    }

    private function sync(Court $court, array $data): void
    {
        $court->amenities()->sync($data['amenity_ids'] ?? []);
        $court->prices()->whereNotIn('time_slot_id', array_keys($data['prices']))->update(['status' => 'INACTIVE']);
        foreach ($data['prices'] as $slotId => $price) {
            if ($price === null || $price === '') {
                $court->prices()->where('time_slot_id', $slotId)->update(['status' => 'INACTIVE']);

                continue;
            } $court->prices()->updateOrCreate(['time_slot_id' => $slotId], ['price' => $price, 'effective_from' => today(), 'effective_to' => null, 'status' => 'ACTIVE']);
        }
    }

    private function storeImages(Court $court, Request $request): void
    {
        foreach ($request->file('images', []) as $file) {
            $court->images()->create(['image' => $file->store('courts', 'public'), 'is_primary' => ! $court->images()->exists(), 'sort_order' => ($court->images()->max('sort_order') ?? 0) + 1]);
        }
    }

    private function removeImages(Court $court, array $ids): void
    {
        foreach ($court->images()->whereIn('id', $ids)->get() as $image) {
            Storage::disk('public')->delete($image->image);
            $image->delete();
        } if (! $court->images()->where('is_primary', true)->exists()) {
            $court->images()->oldest('sort_order')->first()?->update(['is_primary' => true]);
        }
    }

    private function uniqueCode(string $name): string
    {
        $base = Str::upper(Str::slug($name));
        $code = $base;
        for ($i = 2; Court::where('code', $code)->exists(); $i++) {
            $code = $base.'-'.$i;
        }

return $code;
    }

    private function formData(?Court $court = null): array
    {
        return ['court' => $court, 'courtTypes' => CourtType::where('status', 'ACTIVE')->orderBy('name')->get(), 'amenities' => Amenity::where('status', 'ACTIVE')->orderBy('name')->get(), 'timeSlots' => TimeSlot::where('status', 'ACTIVE')->orderBy('start_time')->get()];
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->role === 'ADMIN', 403);
    }
}
