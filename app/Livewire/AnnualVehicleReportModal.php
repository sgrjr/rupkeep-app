<?php

namespace App\Livewire;

use App\Models\Vehicle;
use App\Models\UserLog;
use App\Models\VehicleMaintenanceRecord;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AnnualVehicleReportModal extends Component
{
    public $showModal = false;
    public $vehicleId = null;
    public $selectedYear;
    public $reportData = [];

    protected $listeners = [
        'open-annual-report-modal' => 'openModal',
    ];

    public function mount($vehicleId = null)
    {
        $this->vehicleId = $vehicleId;
        $this->selectedYear = now()->year;
    }
    
    public function getYearsProperty()
    {
        $currentYear = now()->year;
        $years = [];
        for ($i = 0; $i < 10; $i++) {
            $years[] = $currentYear - $i;
        }
        return $years;
    }

    public function openModal()
    {
        $this->showModal = true;
        $this->generateReport();
    }

    public function boot()
    {
        if ($this->vehicleId) {
            $this->listeners['open-annual-report-modal-' . $this->vehicleId] = 'openModal';
        } else {
            $this->listeners['open-annual-report-modal'] = 'openModal';
        }
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->reset(['reportData']);
    }

    // Removed auto-trigger on date updates - user must click "Update Report" button

    public function generateReport()
    {
        $organizationId = Auth::user()->organization_id;
        
        // Use selected year or default to current year
        $year = $this->selectedYear ?? now()->year;
        $start = Carbon::create($year, 1, 1)->startOfDay();
        $end = Carbon::create($year, 12, 31)->endOfDay();

        // Get vehicles - either specific one or all
        $vehiclesQuery = Vehicle::where('organization_id', $organizationId);
        
        if ($this->vehicleId) {
            $vehiclesQuery->where('id', $this->vehicleId);
        }
        
        $vehicles = $vehiclesQuery->orderBy('name')->get();

        $this->reportData = [];
        
        // Debug: Log query parameters (can be removed in production)
        \Log::debug('Annual Vehicle Report', [
            'organization_id' => $organizationId,
            'year' => $year,
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'vehicle_id' => $this->vehicleId,
            'vehicles_count' => $vehicles->count(),
        ]);

        foreach ($vehicles as $vehicle) {
            // Get all logs for this vehicle in the selected year
            // Use started_at if available, otherwise fall back to created_at
            $logs = UserLog::where('vehicle_id', $vehicle->id)
                ->where('organization_id', $organizationId)
                ->where(function($query) use ($start, $end) {
                    $query->where(function($q) use ($start, $end) {
                        // Logs with started_at in the date range
                        $q->whereNotNull('started_at')
                          ->whereBetween('started_at', [$start, $end]);
                    })->orWhere(function($q) use ($start, $end) {
                        // Logs with null started_at but created_at in the date range
                        $q->whereNull('started_at')
                          ->whereBetween('created_at', [$start, $end]);
                    });
                })
                ->orderByRaw('COALESCE(started_at, created_at)')
                ->get();

            // Get maintenance records for this vehicle in the selected year
            $maintenanceRecords = VehicleMaintenanceRecord::where('vehicle_id', $vehicle->id)
                ->where('organization_id', $organizationId)
                ->where(function($query) use ($start, $end) {
                    $query->where(function($q) use ($start, $end) {
                        // Records with performed_at in the date range
                        $q->whereNotNull('performed_at')
                          ->whereBetween('performed_at', [$start, $end]);
                    })->orWhere(function($q) use ($start, $end) {
                        // Records with null performed_at but created_at in the date range
                        $q->whereNull('performed_at')
                          ->whereBetween('created_at', [$start, $end]);
                    });
                })
                ->orderBy('performed_at', 'desc')
                ->get();

            // Debug: Log vehicle and log counts
            \Log::debug('Vehicle Report - Vehicle Check', [
                'vehicle_id' => $vehicle->id,
                'vehicle_name' => $vehicle->name,
                'logs_count' => $logs->count(),
                'maintenance_count' => $maintenanceRecords->count(),
                'year' => $year,
                'date_range' => $start->toDateString() . ' to ' . $end->toDateString(),
            ]);

            // Include vehicle in report if it has either logs OR maintenance records
            if ($logs->isEmpty() && $maintenanceRecords->isEmpty()) {
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

                    // Deadhead miles
                    if ($log->is_deadhead) {
                        $deadheadMiles += $logMiles;
                    }

                    // Personal miles calculation
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

            // Calculate maintenance totals
            $maintenanceTotalCost = $maintenanceRecords->sum('cost') ?? 0;
            $maintenanceCount = $maintenanceRecords->count();
            $maintenanceByType = $maintenanceRecords->groupBy('type')->map->count();

            $this->reportData[] = [
                'vehicle' => $vehicle,
                'total_miles' => $totalMiles,
                'deadhead_miles' => $deadheadMiles,
                'personal_miles' => $personalMiles,
                'billable_miles' => $billableMiles,
                'logs_count' => $logs->count(),
                'maintenance_count' => $maintenanceCount,
                'maintenance_total_cost' => $maintenanceTotalCost,
                'maintenance_by_type' => $maintenanceByType,
                'maintenance_records' => $maintenanceRecords,
            ];
        }
    }

    public function render()
    {
        return view('livewire.annual-vehicle-report-modal');
    }
}
