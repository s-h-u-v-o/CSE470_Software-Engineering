<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Services\LifecycleService;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function updateLifecycle(Request $request, Asset $asset, LifecycleService $service)
    {
        // 1. Validate the request shape
        $request->validate([
            'state' => 'required|string'
        ]);

        try {
            // 2. Call the service (we pass 1 as the actor_id for now, assuming user ID 1 exists)
            $service->transition($asset, $request->state, $actorId = 1);

            return response()->json(['message' => 'State updated successfully', 'asset' => $asset]);
            
        } catch (\Exception $e) {
            // 3. Return the rejection clearly
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}