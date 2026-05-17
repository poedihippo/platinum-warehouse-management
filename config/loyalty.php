<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Loyalty frontend URL
    |--------------------------------------------------------------------------
    |
    | Base URL of the customer-facing loyalty frontend (the verify app).
    | All user-facing links emitted in loyalty emails (email verification,
    | password reset) are built off this host, NOT APP_URL — APP_URL points
    | at the API. Falls back to APP_URL when unset so local/dev still works.
    |
    | Set LOYALTY_FRONTEND_URL in Forge env to:
    |   https://verify.platinumadisentosa.com
    |
    */

    'frontend_url' => env('LOYALTY_FRONTEND_URL', env('APP_URL', 'http://localhost')),

];
