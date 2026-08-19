<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Admin panel URL
    |--------------------------------------------------------------------------
    |
    | Where the administration SPA is served. Invitation links are built
    | against this, not against APP_URL: APP_URL is the API's own address, and
    | sending an invitee to the API would hand them JSON instead of the form
    | that lets them choose a password.
    |
    | The two are the same host only by coincidence in some deployments, so
    | they are configured separately rather than derived from one another.
    |
    */

    'admin_url' => env('ADMIN_URL', 'http://localhost:5173'),

];
