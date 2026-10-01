<?php

namespace App\Http\Controllers;

use App\Services\AnnualVehicleReport;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class MyReportsController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display the reports index page.
     */
    public function index()
    {
        return view('reports.index');
    }

    /**
     * The annual vehicle report. The figures come from
     * App\Services\AnnualVehicleReport, shared with the per-vehicle modal
     * (TASK-476).
     */
    public function annualVehicleReport(Request $request)
    {
        $organizationId = Auth::user()->organization_id;

        $defaults = [
            'start_date' => now()->startOfYear()->format('Y-m-d'),
            'end_date' => now()->endOfYear()->format('Y-m-d'),
        ];

        // Carbon::parse() of whatever was typed used to 500 on garbage and
        // silently show nothing when the dates were the wrong way round.
        $validator = Validator::make($request->only(['start_date', 'end_date']), [
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ], [], ['start_date' => __('start date'), 'end_date' => __('end date')]);

        if ($validator->fails()) {
            session()->flash('error', __('Those dates could not be used (:reason). Showing the current year instead.', [
                'reason' => $validator->errors()->first(),
            ]));

            return redirect()->route('my.reports.annual-vehicle-report');
        }

        $startDate = $request->input('start_date') ?: $defaults['start_date'];
        $endDate = $request->input('end_date') ?: $defaults['end_date'];

        $start = Carbon::createFromFormat('Y-m-d', $startDate)->startOfDay();
        $end = Carbon::createFromFormat('Y-m-d', $endDate)->endOfDay();

        return view('reports.annual-vehicle-report', [
            'reportData' => AnnualVehicleReport::build($organizationId, $start, $end),
            'startDate' => $startDate,
            'endDate' => $endDate,
            'start' => $start,
            'end' => $end,
        ]);
    }
}
