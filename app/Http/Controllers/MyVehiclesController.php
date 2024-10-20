<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Vehicle;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class MyVehiclesController extends Controller
{

    use AuthorizesRequests;

    public function index(Request $request){
        $vehicles = Vehicle::with('user')->where('organization_id', auth()->user()->organization_id)->get();
        return view('vehicles.index', compact('vehicles'));
    }

    public function create(Request $request){
        return view('vehicles.create');
    }

    public function edit(Request $request, int $customer_id){
        $vehicle = Vehicle::where('id', $customer_id)->first();
        return view('vehicles.edit', compact('vehicle'));
    }

    public function store(Request $request){
        if(!$request->has('organization_id')){
            $request->merge([
                'organization_id' => auth()->user()->organization_id
            ]);
        }
        $vehicle = new Vehicle($request->except('_method'));
        $this->authorize('create', $vehicle);
        $vehicle->save();
        return redirect()->route('my.vehicles.index');
    }

    public function update(Request $request, $vehicle){
        $vehicle = Vehicle::find($vehicle);

        if($vehicle && $this->authorize('update', $vehicle)){
           $vehicle->update($request->except('_method'));
        }

        return redirect()->route('customers.index');
    }

    public function destroy(Request $request, $vehicle){

        $vehicle = Vehicle::find($vehicle);

        if($vehicle && $this->authorize('delete', $vehicle)){
           $vehicle->delete();
        }

        return redirect()->route('customers.index');
    }
}
