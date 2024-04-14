<?php

declare(strict_types=1);

namespace Uc\HttpTrafficLogger;

use DateTimeImmutable;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Uc\KafkaProducer\Events\ProduceMessageEvent;
use Uc\KafkaProducer\MessageBuilder;
use Google\Cloud\Storage\StorageClient;

use function config;
use function json_encode;

/**
 * TrafficManager is responsible for:
 * - capturing incoming requests
 * - capturing outgoing responses
 * - generating records
 * - and publishing records.
 */
class TrafficManager
{
    public function __construct(
        protected Dispatcher $dispatcher,
    ) {
    }

    /**
     * Capture given request.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Uc\HttpTrafficLogger\Record
     */
    public function capture(Request $request): Record
    {
        // Fix traffic start time.
        $record = new Record(new DateTimeImmutable());
        $record->captureRequest($request);

        return $record;
    }

    /**
     * Record captured information.
     *
     * @param \Uc\HttpTrafficLogger\Record $record
     *
     * @return void
     */
    public function record(Record $record): void
    {
        $storage = new StorageClient([
            'keyFilePath' => config('http-traffic-logger.key_file_path'),
        ]);
        $bucket = $storage->bucket(config('http-traffic-logger.log_bucket'));
        $filename = date('Y-m-d') ."/".$record->getUuid().'.json';
        // Upload a file to the bucket
        $bucket->upload(
            json_encode($record->dump(), depth: 1024), // Open the local file in read mode
            [
                'name' => $filename,
            ]
        );

        $builder = new MessageBuilder();
        $message = $builder
            ->setTopicName(config('http-traffic-logger.destination_kafka_topic'))
            ->setKey($record->getCreatedAt()->format('c'))
            ->setBody(['cmd' => 'log-http-traffic', 'args' => ['log-file' => $filename]])
            ->getMessage();

        $this->dispatcher->dispatch(
            new ProduceMessageEvent($message)
        );
    }
}
