<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\OperatorAuthorization;
use App\Models\RosterShift;
use Carbon\Carbon;

class StaffingService
{
    /**
     * Check if the asset is currently staffed by a qualified operator.
     */
    public function isStaffed(Asset $asset, Carbon $time = null): bool
    {
        $time = $time ?? now();

        // 1. Get users who are authorized for this asset's category at this time
        $authorizedUserIds = OperatorAuthorization::where('asset_category_id', $asset->asset_category_id)
            ->where('valid_from', '<=', $time)
            ->where('valid_to', '>=', $time)
            ->pluck('user_id');

        if ($authorizedUserIds->isEmpty()) {
            return false; // No one is authorized for this category
        }

        // 2. Check if any of those authorized users have a roster shift covering this time
        $hasRosteredOperator = RosterShift::whereIn('user_id', $authorizedUserIds)
            ->where('start_time', '<=', $time)
            ->where('end_time', '>=', $time)
            ->exists();

        return $hasRosteredOperator;
    }
}