<?php

declare(strict_types=1);

return [
    'content_updated_at' => env('INSTITUTIONAL_CONTENT_UPDATED_AT', '2026-08-27'),
    'support_rate_limit_per_minute' => (int) env('INSTITUTIONAL_SUPPORT_RATE_LIMIT_PER_MINUTE', 5),
];
