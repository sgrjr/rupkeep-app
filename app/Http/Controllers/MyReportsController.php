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

            $previousLog = null;
            $hasBillableOverrides = false;

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

                    // Deadhead miles - only count if explicitly marked as deadhead
                    if ($log->is_deadhead) {
                        $deadheadMiles += $logMiles;
                    }

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

                    // Billable miles - use override if set
                    if ($log->billable_miles !== null && $log->billable_miles >= 0) {
                        $billableMiles += $log->billable_miles;
                        $hasBillableOverrides = true;
                    }
                }

                $previousLog = $log;
            }

            // If no billable_miles overrides were used, calculate: total - personal - deadhead
            if (!$hasBillableOverrides) {
                $billableMiles = max(0, $totalMiles - $personalMiles - $deadheadMiles);
            }

            $reportData[] = [
                'vehicle' => $vehicle,
                'total_miles' => $totalMiles,
                'deadhead_miles' => $deadheadMiles,
                'personal_miles' => $personalMiles,
                'billable_miles' => $billableMiles,
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
