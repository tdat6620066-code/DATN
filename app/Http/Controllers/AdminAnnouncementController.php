<?php

namespace App\Http\Controllers;

use App\Models\Court;
use App\Models\SystemAnnouncement;
use App\Models\User;
use App\Services\AnnouncementService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminAnnouncementController extends Controller
{
    public function __construct(private readonly AnnouncementService $announcements) {}

    public function index(Request $request)
    {
        $announcements = SystemAnnouncement::with(['creator', 'court'])
            ->withCount([
                'notifications as sent_count',
                'notifications as read_count' => fn ($query) => $query->where('is_read', true),
                'notifications as clicked_count' => fn ($query) => $query->whereNotNull('clicked_at'),
            ])
            ->when($request->filled('search'), fn ($query) => $query->where('title', 'like', '%'.$request->search.'%'))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->latest()->paginate(20)->withQueryString();

        $customers = User::where('role', 'CUSTOMER')->orderBy('name')->get(['id', 'name', 'email']);
        $courts = Court::where('status', 'ACTIVE')->orderBy('name')->get(['id', 'name', 'address']);
        $areas = Court::whereNotNull('address')->where('address', '!=', '')->distinct()->orderBy('address')->pluck('address');

        return view('admin.announcements.index', compact('announcements', 'customers', 'courts', 'areas'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:5000'],
            'audience' => ['required', Rule::in(['ALL', 'CUSTOMER', 'EMPLOYEE'])],
            'target_type' => ['required', Rule::in(['AUDIENCE', 'SELECTED', 'COURT', 'AREA'])],
            'target_user_ids' => ['nullable', 'array', 'required_if:target_type,SELECTED'],
            'target_user_ids.*' => ['integer', 'exists:users,id'],
            'court_id' => ['nullable', 'required_if:target_type,COURT', 'exists:courts,id'],
            'area' => ['nullable', 'required_if:target_type,AREA', 'string', 'max:500'],
            'action_url' => ['nullable', 'string', 'max:1000'],
            'scheduled_at' => ['nullable', 'date'],
        ]);
        if ($data['target_type'] !== 'AUDIENCE') $data['audience'] = 'CUSTOMER';
        $data['action_url'] = url('/'.ltrim($data['action_url'] ?: '', '/'));
        $send = ! $data['scheduled_at'] || now()->gte($data['scheduled_at']);
        $announcement = SystemAnnouncement::create($data + ['created_by' => $request->user()->id, 'status' => $send ? 'DRAFT' : 'SCHEDULED']);
        if ($send) $this->announcements->deliver($announcement);

        return back()->with('success', $send ? 'Đã gửi thông báo.' : 'Đã lên lịch thông báo.');
    }

    public function destroy(SystemAnnouncement $announcement)
    {
        abort_if($announcement->status === 'SENT', 422, 'Không thể xóa thông báo đã gửi.');
        $announcement->delete();
        return back()->with('success', 'Đã xóa thông báo.');
    }
}
