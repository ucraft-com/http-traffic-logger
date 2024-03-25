<?php

declare(strict_types=1);

namespace Uc\HttpTrafficLogger;

use DateTimeImmutable;
use Illuminate\Events\Dispatcher;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Uc\KafkaProducer\Events\ProduceMessageEvent;
use Uc\KafkaProducer\MessageBuilder;

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
        protected Filesystem $filesystem,
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
        $relativePath = config('http-traffic-logger.log_dir');
        $path = $relativePath.DIRECTORY_SEPARATOR.$record->getUuid().'.json';
        $this->filesystem->put($path, json_encode($record->dump(), depth: 1024));

        $builder = new MessageBuilder();
        $message = $builder
            ->setTopicName(config('http-traffic-logger.destination_kafka_topic'))
            ->setKey($record->getCreatedAt()->format('c'))
            ->setBody(['cmd' => 'log-http-traffic', 'args' => ['log-file' => $path]])
            ->getMessage();

        $this->dispatcher->dispatch(
            new ProduceMessageEvent($message)
        );
    }
}
