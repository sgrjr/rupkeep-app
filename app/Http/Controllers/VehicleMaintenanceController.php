<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Models\VehicleMaintenanceRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class VehicleMaintenanceController extends Controller
{
    public function store(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $this->authorize('update', $vehicle);

        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(VehicleMaintenanceRecord::types()))],
            'title' => ['nullable', 'string', 'max:255'],
            'performed_at' => ['nullable', 'date'],
            'next_due_at' => ['nullable', 'date', 'after_or_equal:performed_at'],
            'mileage' => ['nullable', 'integer', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);

        $vehicle->maintenanceRecords()->create(array_merge($data, [
            'organization_id' => $vehicle->organization_id,
            'created_by' => Auth::id(),
        ]));

        return back()->with('status', 'maintenance-record-added');
    }
}

