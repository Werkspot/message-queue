<?php

declare(strict_types=1);

namespace Werkspot\MessageQueue\DeliveryQueue\Amqp;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPLazyConnection;
use PhpAmqpLib\Connection\AMQPSSLConnection;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Werkspot\MessageQueue\DeliveryQueue\ConsumerInterface;

final class AmqpConsumer implements ConsumerInterface
{
    /**
     * @var AMQPSSLConnection
     */
    private $connection;

    /**
     * @var AMQPChannel
     */
    private $channel;

    /**
     * @var AmqpMessageHandlerInterface
     */
    private $handler;

    /**
     * @var bool
     */
    private $exitSignalReceived = false;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Setup signals and connection
     */
    public function __construct(
        AMQPLazyConnection $connection,
        AmqpMessageHandlerInterface $handler,
        LoggerInterface $logger = null
    ) {
        if (extension_loaded('pcntl')) {
            if (!defined('AMQP_WITHOUT_SIGNALS')) {
                define('AMQP_WITHOUT_SIGNALS', false);
            }

            pcntl_signal(SIGTERM, [$this, 'signalHandler']);
            pcntl_signal(SIGHUP, [$this, 'signalHandler']);
            pcntl_signal(SIGINT, [$this, 'signalHandler']);
            pcntl_signal(SIGQUIT, [$this, 'signalHandler']);
            pcntl_signal(SIGUSR1, [$this, 'signalHandler']);
            pcntl_signal(SIGUSR2, [$this, 'signalHandler']);
        } else {
            throw new RuntimeException('Unable to process signals');
        }

        $this->connection = $connection;
        $this->handler = $handler;
        $this->logger = $logger ?? new NullLogger();
    }

    public function signalHandler(int $signalNumber): void
    {
        switch ($signalNumber) {
            case SIGTERM:  // 15 : supervisor default stop
            case SIGQUIT:  // 3  : kill -s QUIT
            case SIGINT:   // 2  : ctrl+c
            case SIGHUP:   // 1  : kill -s HUP
                // We COULD always do a $this->channel->close() here, but that would result in the channel being closed,
                // rabbit not receiving the ack and so it will try it again. But the problem with this is that there
                // could have been a command halfway processing, or even worse, just done processing, in that case,
                // we'do do the command again, basically executing it twice. So it's better to just wait, and finish
                // the current/next command, and stop after that
                $this->exitSignalReceived = true;

                // But if we're just waiting and not doing anything of/c we can close now
                if (!$this->handler->isHandlingMessage()) {
                    $this->connection->close();
                }
                break;
            default:
                // Some unknown event... idk what to do? probably not exit... but yeah what then? ... let's just pretend
                // it didn't happen ><
                $this->logger->warning('Got unhandled signal: ' . $signalNumber);
                break;
        }
    }

    public function startConsuming(string $queueName, int $maxSeconds): void
    {
        $start = time();

        $this->channel = $this->connection->channel();
        $this->channel->basic_consume(
            $queueName,
            '',
            false,
            false,
            false,
            false,
            [$this->handler, 'handle']
        );

        while (count($this->channel->callbacks) && !$this->exitSignalReceived) {
            // Wait for the next message to arrive and process it
            $this->channel->wait();

            if (time() > ($start + $maxSeconds)) {
                break;
            }
        }
    }
}
