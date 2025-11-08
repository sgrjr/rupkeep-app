<?php

namespace App\Http\Controllers;

use App\Events\InvoiceReady;
use Illuminate\Http\Request;
use App\Models\UserLog;
use App\Models\Invoice;
use App\Models\PilotCarJob;
use App\Models\JobInvoice;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class MyInvoicesController extends Controller
{

    use AuthorizesRequests;

    public function edit(Request $request, Invoice $invoice){

      $this->authorize('update', $invoice);

      return view('invoices.edit', compact('invoice'));
    }
    public function print(Request $request, Invoice $invoice)
    {
        $user = $request->user();

        if (! $user->is_super && ! $user->isAdmin() && ! $user->isManager()) {
            abort(403);
        }

        $this->authorize('view', $invoice);

        $invoice->loadMissing(['customer', 'organization', 'job']);

        return view('invoices.print', [
            'invoice' => $invoice,
            'values' => is_array($invoice->values) ? $invoice->values : [],
        ]);
    }

    public function update(Request $request, Invoice $invoice){

        $this->authorize('update', $invoice);

        if($request->has('values')){
            $invoice->updateValues($request->values);
        }
        
        if($request->has('delete') && $request->delete === 'on'){
            $job_id = $invoice->pilot_car_job_id;
            $invoice->forceDelete();
            return redirect()->route('my.jobs.show', ['job'=>$job_id]);
        }

        $input = $request->except('values','_method','id','_token','delete');

        if(count($input) > 0){
            if(array_key_exists('paid_in_full', $input)){
                if($input['paid_in_full'] === 'yes'){
                    $input['paid_in_full'] = true;
                }else{
                    $input['paid_in_full'] = false;
                }
            }
            $invoice->update($input);
        }

        return back();
    }

    public function store(Request $request){

        $jobs = PilotCarJob::whereIn('id', $request->invoice_this)->get();

        $invoices = [];

        $jobs->map(function($job)use(&$invoices){
            $invoices[] = $job->invoiceValues();
        });

        foreach($invoices as $i){
            $jobId = $i['job_id'] ?? $i['pilot_car_job_id'] ?? null;

            if (!$jobId) {
                continue;
            }

            $invoice = Invoice::create([
                'paid_in_full' => false,
                'values' => $i,
                'organization_id' => $i['organization_id'],
                'customer_id' => $i['customer_id'],
                'pilot_car_job_id' => $jobId
            ]);

            JobInvoice::create([
                'invoice_id' => $invoice->id,
                'pilot_car_job_id' => $jobId
            ]);

            event(new InvoiceReady($invoice));
        }
        
        return back();
    }

    public function delete(Request $request, $log){
        return $this->destroy($request, $log);
    }
    public function destroy(Request $request, $log){

        $log = UserLog::find($log);

        if($log && $this->authorize('delete', $log)){
           $log->delete();
        }

        return back();
    }

    public function restore(Request $request, $log){
        $log = UserLog::withTrashed()->find($log);

        if($log && $this->authorize('restore', $log)){
           $log->restore();
        }

        return back();
    }

    public function forceDelete(Request $request, $log){

        $log = UserLog::withTrashed()->find($log);

        if($log && $this->authorize('delete', $log)){
           $log->forceDelete();
        }

        return back();
    }
}
