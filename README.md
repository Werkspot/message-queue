# Werkspot \ MessageQueue

[![Author](http://img.shields.io/badge/author-Werkspot-blue.svg?style=flat-square)](https://www.werkspot.com)
[![Software License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE)
[![Latest Version](https://img.shields.io/github/release/werkspot/message-queue.svg?style=flat-square)](https://github.com/werkspot/message-queue/releases)
[![Total Downloads](https://img.shields.io/packagist/dt/werkspot/message-queue.svg?style=flat-square)](https://packagist.org/packages/werkspot/message-queue)

[![Build Status](https://img.shields.io/scrutinizer/build/g/werkspot/message-queue.svg?style=flat-square)](https://scrutinizer-ci.com/g/werkspot/message-queue/build)
[![Coverage Status](https://img.shields.io/scrutinizer/coverage/g/werkspot/message-queue.svg?style=flat-square)](https://scrutinizer-ci.com/g/werkspot/message-queue/code-structure)
[![Quality Score](https://img.shields.io/scrutinizer/g/werkspot/message-queue.svg?style=flat-square)](https://scrutinizer-ci.com/g/werkspot/message-queue)

## What this project is

A library capable of delivering a message to a destination asynchronously, 
as soon as possible or at a specified date and time.

The message to be delivered can be anything but its serialization must be taken care of by a MessageRepositoryInterface,
who's implementation must be provided by the code using this library.

The destination can be specified by any string, but the interpretation of that string, and the effective delivery of 
the message to the destination must be taken care by the MessageDeliveryServiceInterface,
who's implementation must be provided by the code using this library.

This MessageQueue uses two internal queues, one for messages that are scheduled for delivery (the ScheduledQueue, 
using some persistence mechanism like MySQL) and another queue for messages that are in line for delivery 
(the DeliveryQueue, using rabbitMq).

## Why this project exists

A message queue is useful to run asynchronous tasks, as soon as possible or at a specified date and time, 
thus balancing the load on the servers across the time, and allowing for faster responses to users as they will not 
need to wait for tasks to be done inline which can be done async, like for example sending out emails.

On top of this library we can build a Message Bus, which can decide if a message should be delivered in sync or async. 
In turn, on top of that Message Bus we can build a Command Bus, which delivers one message to only one destination, 
or an Event Bus, which can deliver one message to several destinations.

## Usage 

The `MessageQueueService` is the entry point to the message queue.

```php
    $messageQueueService = new MessageQueueService(
        new ScheduledQueueService(
            new MessageRepository(/*...*/) // implemented by the code using this library
        )
    );
    
    $messageQueueService->enqueueMessage(
        $someObjectOrStringOrWhatever,      // some payload to deliver, persisted by the MessageRepository
        '{"deliver_to": "SomeServiceId"}',  // destination to be decoded by the delivery service (MessageDeliveryServiceInterface)
        new DateTimeImmutable(),            // delivery date and time
        5,                                  // priority
        []                                  // some whatever metadata
    );
```

in order to move messages from the _ScheduledQueue_ to the _DeliveryQueue_ we need **one** 
ScheduledQueueToDeliveryQueueWorker to be running in the background. And to move messages from the DeliveryQueue to 
the actual destination we need **at least one** DeliveryQueueToHandlerWorker to be running in the background.

Our `$scheduledQueueWorker` will be run, for example, by a CLI command which will be kept alive by a process management 
tool like Supervisor.

```php
    $scheduledQueueWorker = new ScheduledQueueToDeliveryQueueWorker(
        new ScheduledQueueService(new MessageRepository(/*...*/)),
        new AmqpProducer(new AMQPLazyConnection(/*...*/), new UuidMessageIdGenerator()),
        'some_queue_name',
        new SomeLogger(/*...*/)
    );
    
    $scheduledQueueWorker->moveMessageBatch(50);
```

Like the `$scheduledQueueWorker`, the `$deliveryQueueWorker` is also started by a CLI command and kept alive by a 
process management tool like Supervisor.

```php
    $logger = new SomeLogger(/*...*/);
    
    $deliveryQueueWorker = new DeliveryQueueToHandlerWorker(
        new AmqpConsumer(
            new AMQPLazyConnection(/*...*/),
            new AmqpMessageHandler(
                new MessageHandler(/*...*/),
                new SomeCache(/*...*/),
                new PersistenceClient(/*...*/),
                $logger
            ),
            $logger
        ),
        'some_queue_name'
    );
    
    $deliveryQueueWorker->startConsuming(300);
```

## Installation

To install the library, run the command below and you will get the latest version:

```
composer require werkspot/message-queue
```

## Tests

To execute the tests run:
```bash
make test
```

## Coverage

To generate the test coverage run:
```bash
make test_with_coverage
```

## Code standards

To fix the code standards run:
```bash
make cs-fix
```
