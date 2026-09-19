<?php
namespace App\Http\Controllers;
use App\Models\ContactThread;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class ContactController extends Controller
{
    public function index(Request $request)
    {
        $admin = $request->user()->hasPermission('incidents.manage');
        abort_unless($admin || $request->user()->role === 'CUSTOMER', 403);
        $threads = ContactThread::with('user')->when(! $admin, fn ($q) => $q->where('user_id', $request->user()->id))
            ->when($request->filled('search'), fn ($q) => $q->where('subject', 'like', '%'.$request->search.'%'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))->latest('updated_at')->paginate(15)->withQueryString();
        return view('contacts.index', compact('threads', 'admin'));
    }
    public function store(Request $request)
    {
        abort_unless($request->user()->role === 'CUSTOMER', 403);
        $data = $request->validate(['subject' => ['required', 'string', 'max:255'], 'body' => ['required', 'string', 'max:5000']]);
        $thread = DB::transaction(function () use ($request, $data) {
            $thread = ContactThread::create(['user_id' => $request->user()->id, 'subject' => $data['subject']]);
            $thread->messages()->create(['user_id' => $request->user()->id, 'body' => $data['body']]);
            return $thread;
        });
        return redirect()->route('contacts.show', $thread)->with('success', 'Đã gửi yêu cầu hỗ trợ.');
    }
    private function access(Request $request, ContactThread $thread): void
    {
        abort_unless($request->user()->hasPermission('incidents.manage') || ($request->user()->role === 'CUSTOMER' && $thread->user_id === $request->user()->id), 403);
    }
    public function show(Request $request, ContactThread $thread)
    {
        $this->access($request, $thread);
        return view('contacts.show', ['thread' => $thread, 'messages' => $thread->messages()->with('user')->oldest('id')->paginate(30), 'admin' => $request->user()->hasPermission('incidents.manage')]);
    }
    public function reply(Request $request, ContactThread $thread)
    {
        $this->access($request, $thread);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        DB::transaction(function () use ($request, $thread, $data) {
            $locked = ContactThread::lockForUpdate()->findOrFail($thread->id);
            abort_if($locked->status === 'CLOSED', 422, 'Cuộc hội thoại đã đóng.');
            $locked->messages()->create(['user_id' => $request->user()->id, 'body' => $data['body']]);
            $locked->touch();
        });
        return redirect()->route('contacts.show', ['thread'=>$thread, 'page'=>(int)ceil($thread->messages()->count()/30)])->with('success', 'Đã gửi phản hồi.');
    }
    public function status(Request $request, ContactThread $thread)
    {
        abort_unless($request->user()->hasPermission('incidents.manage'), 403);
        $thread->update($request->validate(['status'=>['required', \Illuminate\Validation\Rule::in(['OPEN','CLOSED'])]]));
        return back()->with('success','Đã cập nhật hội thoại.');
    }
}
