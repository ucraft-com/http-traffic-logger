<?php

declare(strict_types=1);

return [
    /*
     | HTTP traffic will only be logged if this setting is set to true.
     */
    'enabled'                 => env('HTTP_TRAFFIC_LOGGER_ENABLED', false),

    /*
     | Google Cloud Storage bucket where the traffic logs will be stored for later processing.
     */
    'log_bucket'              => env('HTTP_TRAFFIC_LOGGER_BUCKET', 'ucraft-http-traffic-logs'),

    /*
     | Full path of the credentials json file.
     */
    'key_file_path'           => env('HTTP_TRAFFIC_LOGGER_KEY_FILE_PATH'),

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
