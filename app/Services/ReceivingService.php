<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Receiving;
use App\Models\WarrantyTerm;
use App\Models\StatusEvent;
use Illuminate\Support\Facades\DB;
use Exception;
use Carbon\Carbon;

class ReceivingService
{
    /**
     * Record the arrival of a new asset.
     */
    public function receiveAsset(array $data, int $receivedBy): Asset
    {
        // 1. Enforce F02 Acceptance Criterion: Duplicate serial rejected
        if (Asset::where('serial_number', $data['serial_number'])->exists()) {
            throw new Exception("Duplicate serial number: {$data['serial_number']} already exists in the system.");
        }

        // 2. Use a transaction to ensure all records are saved together
        return DB::transaction(function () use ($data, $receivedBy) {
            
            // Create the Asset
            $asset = Asset::create([
                'asset_category_id' => $data['asset_category_id'],
                'name' => $data['name'],
                'serial_number' => $data['serial_number'],
                'lifecycle_state' => 'received_crated', // Default starting state
            ]);

            // Record the Receiving
            Receiving::create([
                'asset_id' => $asset->id,
                'received_by' => $receivedBy,
                'received_date' => $data['received_date'] ?? now(),
            ]);

            // Record the Warranty / AMC Term
            WarrantyTerm::create([
                'asset_id' => $asset->id,
                'provider_name' => $data['provider_name'],
                'term_type' => $data['term_type'], // 'warranty' or 'amc'
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
            ]);

            // Log the initial status event (required for your append-only ledger)
            StatusEvent::create([
                'asset_id' => $asset->id,
                'previous_state' => null,
                'next_state' => 'received_crated',
                'occurred_at' => now(),
                'recorded_at' => now(),
                'actor_id' => $receivedBy,
                'reason' => 'Asset received into inventory',
            ]);

            return $asset;
        });
    }
}