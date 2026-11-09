<?php

return [
    // G1: an asset still received_crated after this many days is flagged.
    'crated_days' => (int) env('GAPS_CRATED_DAYS', 30),

    // G3: a ticket still unassigned (reported or triaged) after this many hours is flagged.
    'unassigned_hours' => (int) env('GAPS_UNASSIGNED_HOURS', 24),
];
