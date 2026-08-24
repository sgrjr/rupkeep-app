<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Models\UserLog;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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
     * Display the annual vehicle report.
     */
    public function annualVehicleReport(Request $request)
    {
        $organizationId = Auth::user()->organization_id;
        
        // Default to current year
        $startDate = $request->input('start_date', now()->startOfYear()->format('Y-m-d'));
        $endDate = $request->input('end_date', now()->endOfYear()->format('Y-m-d'));

        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        // Get all vehicles for the organization
        $vehicles = Vehicle::where('organization_id', $organizationId)
            ->orderBy('name')
            ->get();

        $reportData = [];

        foreach ($vehicles as $vehicle) {
            // Get all logs for this vehicle in the date range
            $logs = UserLog::where('vehicle_id', $vehicle->id)
                ->where('organization_id', $organizationId)
                ->whereBetween('started_at', [$start, $end])
                ->orderBy('started_at')
                ->get();

            if ($logs->isEmpty()) {
                continue;
            }

            $totalMiles = 0;
            $deadheadMiles = 0;
            $personalMiles = 0;
            $billableMiles = 0;
            $releaseMiles = 0;

            $previousLog = null;

            foreach ($logs as $log) {
                // Calculate miles for this log
                $logMiles = 0;
                if ($log->start_mileage && $log->end_mileage) {
                    $logMiles = max(0, $log->end_mileage - $log->start_mileage);
                } elseif ($log->start_job_mileage && $log->end_job_mileage) {
                    $logMiles = max(0, $log->end_job_mileage - $log->start_job_mileage);
                }

                if ($logMiles > 0) {
                    $totalMiles += $logMiles;

                    // Deadhead miles, as recorded on the log (TASK-354). This used
                    // to add the log's ENTIRE odometer span whenever the is_deadhead
                    // flag was ticked, which counted miles spent escorting the load
                    // as deadhead and inflated the figure badly - a 318-mile log with
                    // 129 miles under load reported all 318 as deadhead. Deadhead is
                    // a subset of the miles driven, so it stays inside this block.
                    $deadheadMiles += (float) ($log->dead_head_driven ?? 0);

                    // Personal miles calculation
                    // Gap between end of previous log and start of current log (same vehicle)
                    if ($previousLog && $previousLog->vehicle_id === $log->vehicle_id) {
                        $previousEndMileage = $previousLog->end_mileage ?? $previousLog->end_job_mileage;
                        $currentStartMileage = $log->start_mileage ?? $log->start_job_mileage;

                        if ($previousEndMileage && $currentStartMileage && $currentStartMileage > $previousEndMileage) {
                            $gapMiles = $currentStartMileage - $previousEndMileage;
                            $personalMiles += $gapMiles;
                        }
                    }

                    // Billable miles, from the same accessor the invoice bills
                    // from, so the report and the invoice cannot disagree.
                    $logBillable = (float) ($log->total_billable_miles ?? 0);
                    $billableMiles += $logBillable;

                    // Whatever the trip covered beyond the job itself and the
                    // approach is the drive after release. Tracked, never billed.
                    $releaseMiles += max(0, $logMiles - $logBillable - (float) ($log->dead_head_driven ?? 0));
                }

                $previousLog = $log;
            }

            $reportData[] = [
                'vehicle' => $vehicle,
                'total_miles' => $totalMiles,
                'deadhead_miles' => $deadheadMiles,
                'personal_miles' => $personalMiles,
                'billable_miles' => $billableMiles,
                'release_miles' => $releaseMiles,
                'logs_count' => $logs->count(),
            ];
        }

        return view('reports.annual-vehicle-report', [
            'reportData' => $reportData,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'start' => $start,
            'end' => $end,
        ]);
    }
}
