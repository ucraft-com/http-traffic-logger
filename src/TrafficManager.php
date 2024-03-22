<?php

declare(strict_types=1);

namespace Uc\HttpTrafficLogger;

use DateTimeImmutable;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Uc\KafkaProducer\Events\ProduceMessageEvent;
use Uc\KafkaProducer\MessageBuilder;

use function config;

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
        $builder = new MessageBuilder();
        $message = $builder
            ->setTopicName(config('http-traffic-logger.destination_kafka_topic'))
            ->setKey($record->getCreatedAt()->format('c'))
            ->setBody($record->dump())
            ->getMessage();

        $this->dispatcher->dispatch(
            new ProduceMessageEvent($message)
        );
    }
}
