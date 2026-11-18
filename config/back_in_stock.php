<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Back-in-Stock Alert Configuration
    |--------------------------------------------------------------------------
    |
    | Defines operational thresholds, timeouts, and quota rules for the
    | Back-in-Stock Alerting Pipeline (FV-03-05).
    |
    */

    // Batch quota multiplier against restocked quantity
    'batch_multiplier' => 2.0,

    // Maximum candidates claimed per cron run per event
    'max_cron_batch' => 20,

    // Active lease duration in seconds (fencing window)
    'lease_duration_seconds' => 90,

    // Maximum physical SMTP delivery attempts before failure / manual review
    'max_business_attempts' => 3,

    // Maximum harmless queue concurrency yields before job fails
    'max_concurrency_yields' => 5,

    // Absolute job lifetime deadline in seconds from dispatch (15 minutes)
    'job_deadline_seconds' => 900,

    // Socket timeout for SMTP transport in seconds
    'socket_timeout_seconds' => 15,

    // Cooldown duration before an expired lease is reclaimed by the recovery daemon
    'recovery_stale_threshold_seconds' => 300,

    // Queue connection and queue name for alert dispatch jobs
    'queue' => env('BACK_IN_STOCK_QUEUE', 'default'),
];

