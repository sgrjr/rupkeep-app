<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PilotCarJob as Job;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class MyJobsController extends Controller
{

    use AuthorizesRequests;

    public function index(Request $request){
        $jobs = Job::where('organization_id', auth()->user()->organization_id)->get();
        return view('pilot-car-jobs.index', compact('jobs'));
    }

    public function create(Request $request){
        return view('pilot-car-jobs.create');
    }

    public function edit(Request $request, int $customer_id){
        $job = Job::where('id', $customer_id)->first();
        return view('pilot-car-.edit', compact('job'));
    }

    public function store(Request $request){
        if(!$request->has('organization_id')){
            $request->merge([
                'organization_id' => auth()->user()->organization_id
            ]);
        }
        $job = new Job($request->except('_method'));
        $this->authorize('create', $job);
        $job->save();
        return redirect()->route('my.jobs.edit', ['job'=>$job->id]);
    }

    public function update(Request $request, $job){
        $job = Job::find($job);

        if($job && $this->authorize('update', $job)){
           $job->update($request->except('_method'));
        }

        return back();
    }

    public function destroy(Request $request, $job){

        $job = Job::find($job);

        if($job && $this->authorize('delete', $job)){
           $job->delete();
        }

        return redirect()->route('my.jobs.index');
    }
}
