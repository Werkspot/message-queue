<?php

namespace Werkspot\MessageQueue\DeliveryQueue\Amqp;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPLazyConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Werkspot\MessageQueue\DeliveryQueue\ProducerInterface;
use Werkspot\MessageQueue\Message\MessageInterface;

class AmqpProducer implements ProducerInterface
{
    /**
     * @var AMQPChannel
     */
    private $channel;

    /**
     * @var AMQPLazyConnection
     */
    private $connection;

    /**
     * @var MessageIdGeneratorInterface
     */
    private $idGenerator;

    public function __construct(AMQPLazyConnection $connection, MessageIdGeneratorInterface $idGenerator)
    {
        $this->connection = $connection;
        $this->idGenerator = $idGenerator;
    }

    public function send(MessageInterface $message, string $queueName): void
    {
        $this->setupChannel($queueName);

        $amqpMessage = new AMQPMessage(serialize($message));
        $amqpMessage->set('message_id', $this->idGenerator->generateId());
        $amqpMessage->set('priority', $message->getPriority());
        $amqpMessage->set('timestamp', microtime(true) * 1000);

        $this->channel->basic_publish($amqpMessage, '', $queueName);
    }

    private function setupChannel(string $queueName): void
    {
        if ($this->channel === null) {
            // Don't do this in the constructor, as it will connect() to the rabbit server, making it non-lazy, so
            // only channel() when we really need it
            $this->channel = $this->connection->channel();
        }

        $this->channel->queue_declare(
            $queueName,
            false,
            true,
            false,
            false,
            false,
            new AMQPTable(array(
                "x-max-priority" => 10
            ))
        );
    }
}
