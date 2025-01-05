<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserLog;
use App\Models\Invoice;
use App\Models\PilotCarJob;
use App\Models\JobInvoice;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class MyInvoicesController extends Controller
{

    use AuthorizesRequests;

    public function store(Request $request){

        $jobs = PilotCarJob::whereIn('id', $request->invoice_this)->get();


        $invoices = [];

        $jobs->map(function($job)use(&$invoices){
            $invoices[] = [
                'job_id' => $job->id,
                'organization_id' => $job->organization_id,
                'customer_id' => $job->customer_id,
                'title' => 'INVOICE',
                'logo' => null,
                'bill_from' => [
                    "company" => 'Casco Bay Pilot Car',
                    'attention' => 'Mary Reynolds',
                    "street" => 'P.O. Box 104',
                    'city' => 'Gorham',
                    'state' => 'ME',
                    'zip' => "04038"
                ],
                'bill_to' => [
                    "company" => $job->customer->name,
                    'attention' => null,
                    "street" => $job->customer->street,
                    'city' => $job->customer->city,
                    'state' => $job->customer->state,
                    'zip' => $job->customer->zip,
                ],
                'footer' => 'Casco Bay Pilot Car would like to thank you for your business. Thank you!',
                'truck_driver_name' => $job->getTruckDrivers(true),
                'truck_number' => $job->getTruckNumbers(true),
                'trailer_number' => $job->getTrailerNumbers(true),
                'pickup_address' => $job->pickup_address,
                'delivery_address' => $job->delivery_address,
                'notes' => $job->getInvoiceNotes(true),
                'load_no' => $job->load_no,
                'check_no' => $job->check_no,
                'wait_time_hours' => $job->totalWaitTimeHours(true),
                'extra_load_stops_count' => $job->totalExtraLoadStops(true),
                'dead_head' => $job->getTotalDeadHead(true),
                'tolls' => $job->getTotalTolls(true),
                'hotel' => $job->getTotalHotel(true),
                'extra_chargs' => $job->getExtraCharges(true),
                'rate_code' => $job->rate_code,
                'rate_value' => $job->rate_value,
                'miles' => $job->getTotalMiles(true),
                'total_due' => $job->getTotalDue(true),
            ];
        });

        foreach($invoices as $i){
            $invoice = Invoice::create([
                'paid_in_full' => false,
                'values' => $i,
                'organization_id' => $i['organization_id'],
                'customer_id' => $i['customer_id'],
            ]);

            JobInvoice::create([
                'invoice_id' => $invoice->id,
                'pilot_car_job_id' => $i['job_id']
            ]);
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
