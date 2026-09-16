```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Handshake / push options returned to devices on GET /iclock/cdata
    |--------------------------------------------------------------------------
    */

    // Seconds the device waits after an error before retrying
    'error_delay' => (int) env('ICLOCK_ERROR_DELAY', 30),

    // Heartbeat / request interval (seconds). Protocol recommends 1–30.
    'delay' => (int) env('ICLOCK_DELAY', 10),

    // Timed upload windows (HH:MM;HH:MM)
    'trans_times' => env('ICLOCK_TRANS_TIMES', '00:00;14:00'),

    // Interval upload (minutes)
    'trans_interval' => (int) env('ICLOCK_TRANS_INTERVAL', 1),

    // What data types the server wants. Space or HT separated.
    // Common: TransData AttLog OpLog AttPhoto EnrollUser ChgUser EnrollFP ChgFP UserPic
    'trans_flag' => env('ICLOCK_TRANS_FLAG', 'TransData AttLog OpLog AttPhoto EnrollUser ChgUser EnrollFP ChgFP UserPic'),

    // Timezone offset or name returned to device
    'timezone' => env('ICLOCK_TIMEZONE', config('app.timezone', 'UTC')),

    // 1 = realtime upload preferred
    'realtime' => (int) env('ICLOCK_REALTIME', 1),

    // Max commands returned in a single getrequest poll
    'max_commands_per_poll' => (int) env('ICLOCK_MAX_COMMANDS_PER_POLL', 20),

];
```
