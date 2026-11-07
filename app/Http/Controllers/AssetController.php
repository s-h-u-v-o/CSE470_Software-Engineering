<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\CommissioningChecklist;
use App\Services\LifecycleService;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function index()
    {
        $assets = Asset::with('commissioningChecklists')->get();
        return view('assets.index', compact('assets'));
    }

    public function transition(Request $request, Asset $asset, LifecycleService $service)
    {
        $request->validate(['state' => 'required|string']);

        try {
            $service->transition($asset, $request->state, $actorId = 1);
            return back()->with('success', "Asset successfully moved to {$request->state}!");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // NEW: Show a specific asset's checklist
    public function show(Asset $asset)
    {
        $asset->load('commissioningChecklists');
        return view('assets.show', compact('asset'));
    }

    // NEW: Add a new checklist item
    public function addChecklistItem(Request $request, Asset $asset)
    {
        $request->validate([
            'item_name' => 'required|string|max:255',
            'is_mandatory' => 'boolean'
        ]);

        $asset->commissioningChecklists()->create([
            'item_name' => $request->item_name,
            'is_mandatory' => $request->has('is_mandatory'),
            'is_passed' => false,
        ]);

        return back()->with('success', 'Checklist item added.');
    }

    // NEW: Toggle the pass/fail status of a checklist item
    public function toggleChecklistItem(Asset $asset, CommissioningChecklist $checklist)
    {
        $checklist->update([
            'is_passed' => !$checklist->is_passed,
            'checked_by' => 1 // Hardcoded for now
        ]);

        return back()->with('success', 'Checklist item updated.');
    }
}