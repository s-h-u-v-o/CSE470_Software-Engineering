<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\CommissioningChecklist;
use App\Models\AssetHold;
use App\Models\StatusEvent;
use App\Services\LifecycleService;
use App\Services\ReadinessService;
use App\Services\DowntimeLedger;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function index(ReadinessService $readinessService)
    {
        $assets = Asset::with(['commissioningChecklists', 'tickets', 'holds'])->get();
        foreach ($assets as $asset) {
            $asset->readiness = $readinessService->for($asset);
        }
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
    public function show(Asset $asset, ReadinessService $readinessService, DowntimeLedger $downtimeLedger)
    {
        $asset->load(['commissioningChecklists', 'tickets', 'holds.actor', 'statusEvents']);
        $readiness = $readinessService->for($asset);
        $downtime = $downtimeLedger->forAsset($asset);
        $activeDowntimes = $asset->statusEvents
            ->where('downtime_action', 'start')
            ->filter(fn ($event) => !$asset->statusEvents->contains(fn ($end) => $end->downtime_action === 'end' && $end->cause_key === $event->cause_key));
        return view('assets.show', compact('asset', 'readiness', 'downtime', 'activeDowntimes'));
    }

    public function startDowntime(Request $request, Asset $asset)
    {
        $data = $request->validate([
            'cause_type' => 'required|in:planned,unplanned',
            'reason' => 'required|string|max:2000',
            'ticket_id' => 'nullable|integer|exists:tickets,id',
        ]);
        if (!empty($data['ticket_id']) && $asset->tickets()->whereKey($data['ticket_id'])->doesntExist()) {
            return back()->withErrors(['ticket_id' => 'Select a ticket for this asset.']);
        }

        $causeKey = (string) \Illuminate\Support\Str::uuid();
        $asset->statusEvents()->create([
            'previous_state' => null,
            'next_state' => 'downtime_started',
            'occurred_at' => now(),
            'recorded_at' => now(),
            'cause_type' => $data['cause_type'],
            'downtime_action' => 'start',
            'cause_key' => $causeKey,
            'ticket_id' => $data['ticket_id'] ?? null,
            'actor_id' => $request->user()?->id ?? 1,
            'reason' => $data['reason'],
        ]);

        return back()->with('success', 'Downtime interval started.');
    }

    public function endDowntime(Request $request, Asset $asset, StatusEvent $event)
    {
        abort_unless($event->asset_id === $asset->id && $event->downtime_action === 'start', 404);
        if ($asset->statusEvents()->where('cause_key', $event->cause_key)->where('downtime_action', 'end')->exists()) {
            return back()->with('error', 'This downtime interval has already ended.');
        }

        $asset->statusEvents()->create([
            'previous_state' => 'downtime_started',
            'next_state' => 'downtime_ended',
            'occurred_at' => now(),
            'recorded_at' => now(),
            'cause_type' => $event->cause_type,
            'downtime_action' => 'end',
            'cause_key' => $event->cause_key,
            'ticket_id' => $event->ticket_id,
            'actor_id' => $request->user()?->id ?? 1,
            'reason' => 'Downtime resolved: ' . ($event->reason ?? ''),
        ]);

        return back()->with('success', 'Downtime interval ended.');
    }

    public function createHold(Request $request, Asset $asset)
    {
        $data = $request->validate([
            'type' => 'required|in:quarantine,out_of_service',
            'reason' => 'required|string|max:2000',
        ]);

        $asset->holds()->create($data + [
            'started_at' => now(),
            'actor_id' => $request->user()?->id ?? 1,
        ]);

        return back()->with('success', 'Readiness hold activated.');
    }

    public function releaseHold(Request $request, Asset $asset, AssetHold $hold)
    {
        abort_unless($hold->asset_id === $asset->id, 404);
        if ($hold->ended_at === null) {
            $hold->update(['ended_at' => now()]);
        }

        return back()->with('success', 'Readiness hold released.');
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
