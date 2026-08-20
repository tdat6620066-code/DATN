<?php

namespace App\Http\Controllers;

use App\Models\CourtIncident;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminIncidentController extends Controller
{
    public function index(Request $request) { $incidents=CourtIncident::with(['court','reporter'])->when($request->status,fn($q,$s)=>$q->where('status',$s))->latest()->paginate(20)->withQueryString(); return view('admin.incidents.index',compact('incidents')); }
    public function update(CourtIncident $incident, Request $request) { $data=$request->validate(['status'=>['required',Rule::in(['OPEN','IN_PROGRESS','RESOLVED','CLOSED'])],'resolution_note'=>'nullable|string|max:3000']); $incident->update($data+['resolved_at'=>in_array($data['status'],['RESOLVED','CLOSED'],true)?now():null]); if($data['status']==='CLOSED' && $incident->court->operational_status==='MAINTENANCE') $incident->court->update(['operational_status'=>'AVAILABLE','status_reason'=>null]); return back()->with('success','Đã cập nhật sự cố.'); }
}
