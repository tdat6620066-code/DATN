<?php

namespace App\Http\Controllers;

use App\Models\SystemAnnouncement;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminAnnouncementController extends Controller
{
    public function index() { $announcements = SystemAnnouncement::with('creator')->latest()->paginate(20); return view('admin.announcements.index', compact('announcements')); }
    public function store(Request $request) { $data=$request->validate(['title'=>'required|string|max:255','content'=>'required|string|max:5000','audience'=>['required',Rule::in(['ALL','CUSTOMER','EMPLOYEE'])],'scheduled_at'=>'nullable|date']); $send=!$data['scheduled_at']||now()->gte($data['scheduled_at']); SystemAnnouncement::create($data+['created_by'=>$request->user()->id,'status'=>$send?'SENT':'SCHEDULED','sent_at'=>$send?now():null]); return back()->with('success',$send?'Đã gửi thông báo.':'Đã lên lịch thông báo.'); }
    public function destroy(SystemAnnouncement $announcement) { abort_if($announcement->status==='SENT',422,'Không thể xóa thông báo đã gửi.'); $announcement->delete(); return back()->with('success','Đã xóa thông báo.'); }
}
