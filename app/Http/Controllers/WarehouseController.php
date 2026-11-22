<?php

namespace App\Http\Controllers;

use App\Models\Camera;
use App\Models\Warehouse;
use App\Services\WithdrawalWorkflow;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function index() { return view('warehouses.index', ['warehouses' => Warehouse::with('cameras')->orderBy('name')->get()]); }

    public function store(Request $request, WithdrawalWorkflow $workflow)
    {
        $data = $request->validate(['name' => 'required|string|max:100', 'location' => 'required|string|max:255']);
        $warehouse = Warehouse::create($data);
        $workflow->audit(null, $request->user()->id, 'warehouse_created', ['warehouse_id' => $warehouse->id, 'name' => $warehouse->name]);
        return back()->with('success', 'Gudang ditambahkan.');
    }

    public function camera(Request $request, Warehouse $warehouse, WithdrawalWorkflow $workflow)
    {
        $data = $request->validate(['name' => 'required|string|max:100', 'status' => 'required|in:online,offline']);
        $camera = $warehouse->cameras()->create($data);
        $workflow->audit(null, $request->user()->id, 'camera_created', ['warehouse_id' => $warehouse->id, 'camera_id' => $camera->id]);
        return back()->with('success', 'Kamera ditambahkan.');
    }

    public function updateCamera(Request $request, Camera $camera, WithdrawalWorkflow $workflow)
    {
        $data = $request->validate(['status' => 'required|in:online,offline']);
        $camera->update($data);
        $workflow->audit(null, $request->user()->id, 'camera_updated', ['camera_id' => $camera->id, 'status' => $camera->status]);
        return back()->with('success', 'Status kamera diperbarui.');
    }
}
