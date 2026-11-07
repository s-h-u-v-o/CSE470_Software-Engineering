<?php

return [
    // Major tickets are non-blocking unless the facility explicitly enables this rule.
    'major_blocks' => env('READINESS_MAJOR_TICKETS_BLOCK', false),
];
