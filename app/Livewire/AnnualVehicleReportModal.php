<?php

namespace App\Livewire;

use App\Services\AnnualVehicleReport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

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

    /**
     * The figures come from App\Services\AnnualVehicleReport, shared with the
     * full-page report (TASK-476); this component only picks the year.
     */
    public function generateReport()
    {
        // Fleet reporting is a staff view (TASK-442).
        abort_unless(auth()->user()?->isSuper() || auth()->user()?->isEmployee(), 403);

        $year = (int) ($this->selectedYear ?: now()->year);

        // The picker offers the last ten years; anything else is a typo.
        if (! in_array($year, $this->years, true)) {
            $year = now()->year;
            $this->selectedYear = $year;
        }

        $this->reportData = AnnualVehicleReport::build(
            Auth::user()->organization_id,
            Carbon::create($year, 1, 1)->startOfDay(),
            Carbon::create($year, 12, 31)->endOfDay(),
            $this->vehicleId ? (int) $this->vehicleId : null,
        );
    }

    public function render()
    {
        return view('livewire.annual-vehicle-report-modal');
    }
}
