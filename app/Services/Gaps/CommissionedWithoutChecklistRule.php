<?php

namespace App\Services\Gaps;

use App\Models\Asset;
use Illuminate\Support\Carbon;

/**
 * G7: lifecycle says commissioned without a passed commissioning checklist.
 * LifecycleService blocks this through the UI. The rule catches legacy or directly edited data.
 */
class CommissionedWithoutChecklistRule implements GapRule
{
    public function code(): string
    {
        return 'G7';
    }

    public function title(): string
    {
        return 'Commissioned without a passed checklist';
    }

    public function evaluate(Carbon $now): array
    {
        $findings = [];

        $assets = Asset::query()
            ->where('lifecycle_state', 'commissioned')
            ->with('commissioningChecklists')
            ->get();

        foreach ($assets as $asset) {
            $mandatory = $asset->commissioningChecklists->where('is_mandatory', true);

            if ($mandatory->isEmpty()) {
                $findings[] = new GapFindingData(
                    'G7', 'asset', $asset->id,
                    "Asset #{$asset->id} ({$asset->name}) is commissioned but has no mandatory checklist items.",
                    ['asset_id' => $asset->id, 'lifecycle_state' => $asset->lifecycle_state, 'mandatory_items' => 0],
                    'Add and complete the mandatory commissioning checklist, or correct the lifecycle record.',
                    'no_mandatory_items',
                );
                continue;
            }

            $failed = $mandatory->where('is_passed', false);
            if ($failed->isNotEmpty()) {
                $ids = $failed->pluck('id')->sort()->values()->all();
                $findings[] = new GapFindingData(
                    'G7', 'asset', $asset->id,
                    "Asset #{$asset->id} ({$asset->name}) is commissioned but mandatory items are not passed: "
                        . $failed->pluck('item_name')->join(', ') . '.',
                    ['asset_id' => $asset->id, 'lifecycle_state' => $asset->lifecycle_state, 'failed_item_ids' => $ids],
                    'Pass the listed checklist items, or correct the lifecycle record.',
                    'failed_items:' . implode(',', $ids),
                );
            }
        }

        return $findings;
    }
}
