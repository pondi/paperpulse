<?php

return [
    'required' => array_values(array_filter(array_map('trim', explode(',', env('HEALTH_REQUIRED_SERVICES', 'database,migrations,redis,queue'))))),
];
