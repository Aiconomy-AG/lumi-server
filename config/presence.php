<?php

return [
    'heartbeat_interval_seconds' => (int) env('PRESENCE_HEARTBEAT_INTERVAL_SECONDS', 25),
    'offline_ttl_seconds' => (int) env('PRESENCE_OFFLINE_TTL_SECONDS', 90),
];
