<?php

namespace App\Services\Gaps;

use Illuminate\Support\Carbon;

interface GapRule
{
    /** Gap code from the SRS, for example G7. */
    public function code(): string;

    public function title(): string;

    /** @return list<GapFindingData> */
    public function evaluate(Carbon $now): array;
}
