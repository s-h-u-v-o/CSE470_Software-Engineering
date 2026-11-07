<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\StatusEvent;
use Exception;

class LifecycleService
{
    // Section 6A: The only valid transitions
    protected array $allowedTransitions = [
        'received_crated' => ['installed'],
        'installed' => ['commissioned'],
        'commissioned' => ['retired'],
        'retired' => [], 
    ];

    public function transition(Asset $asset, string $newState, int $actorId): Asset
    {
        $currentState = $asset->lifecycle_state;

        // 1. Enforce the basic state machine rules
        if (!in_array($newState, $this->allowedTransitions[$currentState])) {
            throw new Exception("Invalid lifecycle transition from '{$currentState}' to '{$newState}'.");
        }

        // 2. The Commissioning Gate
        if ($newState === 'commissioned') {
            $mandatoryItems = $asset->commissioningChecklists()->where('is_mandatory', true)->get();
            
            // If there are no checklist items at all, block it
            if ($mandatoryItems->isEmpty()) {
                throw new Exception("Cannot commission asset: No mandatory checklist items found.");
            }

            // If any mandatory item has not passed, block it
            $failedItems = $mandatoryItems->where('is_passed', false);
            if ($failedItems->isNotEmpty()) {
                $failedNames = $failedItems->pluck('item_name')->join(', ');
                throw new Exception("Cannot commission asset. Failed/pending items: {$failedNames}");
            }
        }

        // 3. Update the asset
        $asset->lifecycle_state = $newState;
        $asset->save();

        // 4. Record history
        StatusEvent::create([
            'asset_id' => $asset->id,
            'previous_state' => $currentState,
            'next_state' => $newState,
            'occurred_at' => now(),
            'recorded_at' => now(),
            'actor_id' => $actorId,
            'reason' => 'Lifecycle transition',
        ]);

        return $asset;
    }
}