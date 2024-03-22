<?php

declare(strict_types=1);

return [
    /*
     | Name of the Apache Kafka topic where the captured data should be produced.
     */
    'destination_kafka_topic' => env('HTTP_TRAFFIC_LOGGER_TOPIC', 'http-traffic-logs')
];
