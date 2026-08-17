<?php

namespace App\Http\Controllers;

use App\Models\CourtType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminCourtTypeController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAdmin($request);
        $courtTypes = CourtType::withCount('courts')->orderBy('name')->paginate(15);

        return view('admin.court-types.index', compact('courtTypes'));
    }

    public function create(Request $request)
    {
        $this->authorizeAdmin($request);

        return view('admin.court-types.form', ['courtType' => null]);
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin($request);
        CourtType::create($this->validated($request) + ['status' => 'ACTIVE']);

        return redirect()->route('admin.court-types.index')->with('success', 'Đã thêm loại sân.');
    }

    public function edit(CourtType $courtType, Request $request)
    {
        $this->authorizeAdmin($request);

        return view('admin.court-types.form', compact('courtType'));
    }

    public function update(CourtType $courtType, Request $request)
    {
        $this->authorizeAdmin($request);
        $courtType->update($this->validated($request, $courtType));

        return redirect()->route('admin.court-types.index')->with('success', 'Đã cập nhật loại sân.');
    }

    public function destroy(CourtType $courtType, Request $request)
    {
        $this->authorizeAdmin($request);
        if ($courtType->courts()->exists()) {
            $message = 'Không thể xóa loại sân đang được sử dụng.';

            return $request->expectsJson() ? response()->json(['message' => $message], 422) : back()->with('error', $message);
        } $courtType->delete();

        return redirect()->route('admin.court-types.index')->with('success', 'Đã xóa loại sân.');
    }

    private function validated(Request $request, ?CourtType $courtType = null): array
    {
        return $request->validate(['name' => ['required', 'string', 'max:255', Rule::unique('court_types', 'name')->ignore($courtType)], 'description' => ['nullable', 'string', 'max:2000']]);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->role === 'ADMIN', 403);
    }
}
