<?php

declare(strict_types=1);

return [
    /*
     | Relative path where the traffic logs will be stored for later processing.
     | Path is relative because the actual storage instance will be passed to the logger service.
     */
    'log_dir'                 => env('HTTP_TRAFFIC_LOG_DIR', 'http_traffic_logs'),

    /*
     | Name of the Apache Kafka topic where the captured data should be produced.
     */
    'destination_kafka_topic' => env('HTTP_TRAFFIC_LOGGER_TOPIC', 'http-traffic-logs')
];
