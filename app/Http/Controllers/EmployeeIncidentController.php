<?php

namespace App\Http\Controllers;

use App\Models\{Court, CourtIncident};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EmployeeIncidentController extends Controller
{
    public function index(Request $request) { abort_unless($request->user()->hasPermission('incidents.manage'), 403); $incidents = CourtIncident::with(['court','reporter'])->latest()->paginate(20); $courts = Court::orderBy('name')->get(); return view('employee.incidents.index', compact('incidents','courts')); }
    public function store(Request $request) {
        abort_unless($request->user()->hasPermission('incidents.manage'), 403);
        $data = $request->validate(['court_id'=>'required|exists:courts,id','type'=>'required|string|max:100','severity'=>['required',Rule::in(['LOW','MEDIUM','HIGH','CRITICAL'])],'description'=>'required|string|max:3000','images.*'=>'image|max:4096']);
        $paths = collect($request->file('images', []))->map(fn ($file) => $file->store('incidents','public'))->all();
        DB::transaction(function () use ($request,$data,$paths) { $incident = CourtIncident::create($data + ['incident_code'=>'SC-'.now()->format('YmdHis').'-'.random_int(100,999),'reported_by'=>$request->user()->id,'images'=>$paths]); if (in_array($incident->severity,['HIGH','CRITICAL'],true)) $incident->court->update(['operational_status'=>'MAINTENANCE','status_reason'=>$incident->description]); });
        return back()->with('success','Báo cáo sự cố thành công.');
    }
}
