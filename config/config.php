<?php

declare(strict_types=1);

return [
    /*
     | HTTP traffic will only be logged if this setting is set to true.
     */
    'enabled'                 => env('HTTP_TRAFFIC_LOGGER_ENABLED', true),

    /*
     | Relative path where the traffic logs will be stored for later processing.
     | Path is relative because the actual storage instance will be passed to the logger service.
     */
    'log_dir'                 => env('HTTP_TRAFFIC_LOG_DIR', 'http_traffic_logs'),

    /*
     | Name of the Apache Kafka topic where the captured data should be produced.
     */
    'destination_kafka_topic' => env('HTTP_TRAFFIC_LOGGER_TOPIC', 'http-traffic-logs'),

    /*
     | Specifies list of HTTP request methods that should be logged.
     */
    'request_methods'         => [
        'GET',
//        'HEAD',
        'POST',
        'PUT',
        'DELETE',
//        'CONNECT',
//        'OPTIONS',
//        'TRACE',
        'PATCH',
    ],
];
