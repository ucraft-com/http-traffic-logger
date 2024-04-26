<?php

declare(strict_types=1);

namespace Uc\HttpTrafficLogger;

use DateTimeImmutable;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;
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
        try {
            Redis::connection(config('http-traffic-logger.redis_connection'))
                ->client()
                ->hSet(
                    config('http-traffic-logger.redis_key'),
                    (string)$record->getUuid(),
                    json_encode($record->dump(), depth: 1024)
                );

            $builder = new MessageBuilder();
            $message = $builder
                ->setTopicName(config('http-traffic-logger.destination_kafka_topic'))
                ->setKey($record->getCreatedAt()->format('c'))
                ->setBody(['cmd' => 'log-http-traffic', 'args' => ['log-key' => (string)$record->getUuid()]])
                ->getMessage();

            $this->dispatcher->dispatch(
                new ProduceMessageEvent($message)
            );
        } catch (Throwable $throwable) {
            Log::error('Something went wrong during writing the HTTP Traffic logs.');
            Log::error($throwable->getMessage(), ['http-traffic-logger-error' => $throwable]);
        }
    }
}
