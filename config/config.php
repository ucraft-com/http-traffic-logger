<?php

declare(strict_types=1);

return [
    /*
     | HTTP traffic will only be logged if this setting is set to true.
     */
    'enabled'                 => env('HTTP_TRAFFIC_LOGGER_ENABLED', false),

    /*
     | Redis database connection where the traffic logs will be stored for later processing.
     */
    'redis_connection'              => env('HTTP_TRAFFIC_LOGGER_REDIS_CONNECTION', 'default'),

    /*
     | Key of the Redis data structure where the logs will be stored for later processing.
     */
    'redis_key'           => env('HTTP_TRAFFIC_LOGGER_REDIS_KEY', 'http-traffic-logs'),

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
