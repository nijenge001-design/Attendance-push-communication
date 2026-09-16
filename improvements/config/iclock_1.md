```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Handshake / push options returned to devices on GET /iclock/cdata
    |--------------------------------------------------------------------------
    */

    // Maximum protocol version THIS server implements (Appendix 6).
    // Device and server use min(device pushver, this value).
    'push_prot_ver' => env('ICLOCK_PUSH_PROT_VER', '2.4.1'),

    // Reported as ServerVer= in handshake (may match push_prot_ver).
    'server_ver' => env('ICLOCK_SERVER_VER', '2.4.1'),

    // Seconds the device waits after an error before retrying
    'error_delay' => (int) env('ICLOCK_ERROR_DELAY', 30),

    // Heartbeat / request interval (seconds). Protocol recommends 2–60.
    'delay' => (int) env('ICLOCK_DELAY', 10),

    // Timed upload windows (HH:MM;HH:MM)
    'trans_times' => env('ICLOCK_TRANS_TIMES', '00:00;14:00'),

    // Interval upload (minutes)
    'trans_interval' => (int) env('ICLOCK_TRANS_INTERVAL', 1),

    // What data types the server wants (Format II recommended for new servers)
    'trans_flag' => env(
        'ICLOCK_TRANS_FLAG',
        'TransData AttLog OpLog AttPhoto EnrollUser ChgUser EnrollFP ChgFP UserPic'
    ),

    // Integer hour offset preferred by many firmwares (e.g. 3 for EAT/CAT).
    // Protocol also allows minute offsets when |value| > 60.
    'timezone' => env('ICLOCK_TIMEZONE', 3),

    // 1 = realtime upload preferred
    'realtime' => (int) env('ICLOCK_REALTIME', 1),

    // Max commands returned in a single getrequest poll
    'max_commands_per_poll' => (int) env('ICLOCK_MAX_COMMANDS_PER_POLL', 20),

    /*
    |--------------------------------------------------------------------------
    | PushOptions (when device sends PushOptionsFlag=1)
    |--------------------------------------------------------------------------
    */

    'push_options_flag' => (int) env('ICLOCK_PUSH_OPTIONS_FLAG', 1),

    // Comma-separated keys the server asks the device to report
    'push_options' => env(
        'ICLOCK_PUSH_OPTIONS',
        'FingerFunOn,FaceFunOn,FvFunOn,PalmFunOn,PhotoFunOn'
    ),

    /*
    |--------------------------------------------------------------------------
    | Multi-bio (documented from protocol ~2.4.1+)
    | Colon-separated 0/1 flags per bio type index.
    |--------------------------------------------------------------------------
    */

    // Minimum negotiated version required to emit MultiBio* lines
    'multi_bio_min_ver' => env('ICLOCK_MULTI_BIO_MIN_VER', '2.4.1'),

    'multi_bio_data_support' => env(
        'ICLOCK_MULTI_BIO_DATA_SUPPORT',
        '0:0:0:0:0:0:0:0:0:1:1'
    ),

    'multi_bio_photo_support' => env(
        'ICLOCK_MULTI_BIO_PHOTO_SUPPORT',
        '0:0:0:0:0:0:0:0:0:1:1'
    ),

    // Cache TTL for negotiated protocol per SN (seconds)
    'proto_cache_ttl' => (int) env('ICLOCK_PROTO_CACHE_TTL', 604800), // 7 days

];
```
