<?php

namespace App\Livewire;

use App\Models\Vehicle;
use App\Models\UserLog;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AnnualVehicleReportModal extends Component
{
    public $showModal = false;
    public $vehicleId = null;
    public $startDate;
    public $endDate;
    public $reportData = [];

    protected $listeners = [
        'open-annual-report-modal' => 'openModal',
    ];

    public function mount($vehicleId = null)
    {
        $this->vehicleId = $vehicleId;
        $this->startDate = now()->startOfYear()->format('Y-m-d');
        $this->endDate = now()->endOfYear()->format('Y-m-d');
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

    public function updatedStartDate()
    {
        $this->generateReport();
    }

    public function updatedEndDate()
    {
        $this->generateReport();
    }

    public function generateReport()
    {
        $organizationId = Auth::user()->organization_id;
        
        $start = Carbon::parse($this->startDate)->startOfDay();
        $end = Carbon::parse($this->endDate)->endOfDay();

        // Get vehicles - either specific one or all
        $vehiclesQuery = Vehicle::where('organization_id', $organizationId);
        
        if ($this->vehicleId) {
            $vehiclesQuery->where('id', $this->vehicleId);
        }
        
        $vehicles = $vehiclesQuery->orderBy('name')->get();

        $this->reportData = [];

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

            $this->reportData[] = [
                'vehicle' => $vehicle,
                'total_miles' => $totalMiles,
                'deadhead_miles' => $deadheadMiles,
                'personal_miles' => $personalMiles,
                'billable_miles' => $billableMiles,
                'logs_count' => $logs->count(),
            ];
        }
    }

    public function render()
    {
        return view('livewire.annual-vehicle-report-modal');
    }
}
