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

        // 1. Enforce the rules
        if (!in_array($newState, $this->allowedTransitions[$currentState])) {
            throw new Exception("Invalid lifecycle transition from '{$currentState}' to '{$newState}'.");
        }

        // 2. Update the asset
        $asset->lifecycle_state = $newState;
        $asset->save();

        // 3. Record history in the append-only ledger (Section 10)
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