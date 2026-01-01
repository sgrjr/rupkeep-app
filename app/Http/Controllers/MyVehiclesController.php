<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\VehicleMaintenanceRecord;
use Illuminate\Http\Request;
use App\Models\Vehicle;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class MyVehiclesController extends Controller
{

    use AuthorizesRequests;

    public function index(Request $request)
    {
        $showDeleted = $request->boolean('show_deleted', false);
        
        $query = Vehicle::query()
            ->with(['currentAssignment'])
            ->forOrganization($request->user()->organization_id);
        
        if ($showDeleted) {
            $query->withTrashed();
        }
        
        $vehicles = $query
            ->orderByRaw('CASE WHEN deleted_at IS NULL THEN 0 ELSE 1 END')
            ->orderBy('name')
            ->get();

        return view('vehicles.index', compact('vehicles', 'showDeleted'));
    }

    public function create(Request $request)
    {
        return view('vehicles.create');
    }

    public function edit(Request $request, Vehicle $vehicle)
    {
        $this->authorize('view', $vehicle);

        $vehicle->load([
            'maintenanceRecords.creator',
            'currentAssignment',
            'organization',
        ]);

        $drivers = User::query()
            ->where('organization_id', $request->user()->organization_id)
            ->whereIn('organization_role', [
                User::ROLE_EMPLOYEE_STANDARD,
                User::ROLE_EMPLOYEE_MANAGER,
                User::ROLE_ADMIN,
            ])
            ->orderBy('name')
            ->get();

        return view('vehicles.edit', [
            'vehicle' => $vehicle,
            'drivers' => $drivers,
            'maintenanceTypes' => VehicleMaintenanceRecord::types(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'odometer' => ['nullable', 'integer', 'min:0'],
        ]);

        $vehicle = new Vehicle([
            'name' => $data['name'],
            'organization_id' => $request->user()->organization_id,
            'odometer' => $data['odometer'] ?? null,
            'odometer_updated_at' => $data['odometer'] ? now() : null,
        ]);

        $this->authorize('create', $vehicle);

        $vehicle->save();

        return redirect()
            ->route('my.vehicles.edit', $vehicle)
            ->with('status', 'vehicle-created');
    }

    public function update(Request $request, Vehicle $vehicle)
    {
        $this->authorize('update', $vehicle);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'odometer' => ['nullable', 'integer', 'min:0'],
            'last_service_mileage' => ['nullable', 'integer', 'min:0'],
            'last_oil_change_at' => ['nullable', 'date'],
            'next_oil_change_due_at' => ['nullable', 'date', 'after_or_equal:last_oil_change_at'],
            'last_inspection_at' => ['nullable', 'date'],
            'next_inspection_due_at' => ['nullable', 'date', 'after_or_equal:last_inspection_at'],
            'current_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'current_assignment_started_at' => ['nullable', 'date'],
            'current_assignment_notes' => ['nullable', 'string', 'max:2000'],
            'is_in_service' => ['sometimes', 'boolean'],
        ]);

        if (! empty($data['current_user_id'])) {
            $isValidAssignment = User::query()
                ->where('id', $data['current_user_id'])
                ->where('organization_id', $request->user()->organization_id)
                ->exists();

            if (! $isValidAssignment) {
                unset($data['current_user_id']);
            }
        } else {
            $data['current_user_id'] = null;
            $data['current_assignment_started_at'] = null;
            $data['current_assignment_notes'] = null;
        }

        if (! array_key_exists('is_in_service', $data)) {
            $data['is_in_service'] = $request->boolean('is_in_service');
        }

        $vehicle->update($data);

        return redirect()
            ->route('my.vehicles.edit', $vehicle)
            ->with('status', 'vehicle-updated');
    }

    public function destroy(Request $request, Vehicle $vehicle)
    {
        $this->authorize('delete', $vehicle);

        $vehicle->delete();

        return redirect()->route('my.vehicles.index');
    }

    public function restore(Request $request, int $vehicle)
    {
        $model = Vehicle::withTrashed()
            ->forOrganization($request->user()->organization_id)
            ->findOrFail($vehicle);

        $this->authorize('restore', $model);

        $model->restore();

        return redirect()
            ->route('my.vehicles.index')
            ->with('status', 'vehicle-restored');
    }

    public function forceDestroy(Request $request, int $vehicle)
    {
        $model = Vehicle::withTrashed()
            ->forOrganization($request->user()->organization_id)
            ->findOrFail($vehicle);

        $this->authorize('forceDelete', $model);

        $model->forceDelete();

        return redirect()
            ->route('my.vehicles.index')
            ->with('status', 'vehicle-deleted');
    }
}
