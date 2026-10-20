<?php

use App\Http\Middleware\RequireTestingEnvironment;

return [
    'middleware' => ['web', RequireTestingEnvironment::class],
];
