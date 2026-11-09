<?php

namespace App\Services\Gaps;

use App\Models\Asset;
use Illuminate\Support\Carbon;

/**
 * G1: asset remains received_crated longer than a configured threshold.
 * TODO(F02): switch from assets.created_at to Receiving.received_at once receiving exists.
 */
class CrateTooLongRule implements GapRule
{
    public function code(): string
    {
        return 'G1';
    }

    public function title(): string
    {
        return 'Asset crated too long';
    }

    public function evaluate(Carbon $now): array
    {
        $days = (int) config('gaps.crated_days', 30);
        $cutoff = $now->copy()->subDays($days);
        $findings = [];

        $assets = Asset::query()
            ->where('lifecycle_state', 'received_crated')
            ->where('created_at', '<=', $cutoff)
            ->get();

        foreach ($assets as $asset) {
            $age = (int) floor($asset->created_at->diffInDays($now, true));
            $findings[] = new GapFindingData(
                'G1', 'asset', $asset->id,
                "Asset #{$asset->id} ({$asset->name}) has been received_crated for {$age} days (threshold {$days}).",
                ['asset_id' => $asset->id, 'lifecycle_state' => $asset->lifecycle_state, 'since' => $asset->created_at->toIso8601String()],
                'Install and commission the asset, or record why it remains in storage.',
            );
        }

        return $findings;
    }
}
