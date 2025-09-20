<?php

return [
    'api_key' => config('ai.providers.openai.api_key'),
    'organization' => config('ai.providers.openai.organization'),
    'request_timeout' => config('ai.providers.openai.timeout', 60),
];
