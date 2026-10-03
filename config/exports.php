<?php

return [
    'immediate_limit' => 50,
    'chunk_size' => 100,
    'active_per_user' => 2,
    'expires_hours' => 24,
    'pdf_binary' => env('EXPORT_PDF_BINARY', '/usr/bin/gs'),
];
