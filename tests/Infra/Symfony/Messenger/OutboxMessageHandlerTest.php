<?php

declare(strict_types=1);

namespace Lingoda\DomainEventsBundle\Tests\Infra\Symfony\Messenger;

use Lingoda\DomainEventsBundle\Domain\Model\DomainEvent;
use Lingoda\DomainEventsBundle\Infra\Symfony\Messenger\OutboxMessage;
use Lingoda\DomainEventsBundle\Infra\Symfony\Messenger\OutboxMessageHandler;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\RoutableMessageBus;
use Symfony\Component\Messenger\Stamp\BusNameStamp;

final class OutboxMessageHandlerTest extends TestCase
{
    private MockObject $messageBus;
    private OutboxMessageHandler $outboxMessageHandler;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(RoutableMessageBus::class);
        $this->outboxMessageHandler = new OutboxMessageHandler($this->messageBus, 'bus-name');
    }

    public function testCanHandleOutboxMessage(): void
    {
        $outboxMessageMock = $this->createMock(OutboxMessage::class);
        $domainEventMock = $this->createMock(DomainEvent::class);
        $outboxMessageMock->expects($this->once())->method('getDomainEvent')->willReturn($domainEventMock);
        $this->messageBus->expects($this->once())->method('dispatch')->with(
            $this->callback(static function (Envelope $envelope) {
                $busNameStamp = $envelope->last(BusNameStamp::class);

                return $busNameStamp !== null && $busNameStamp->getBusName() === 'bus-name';
            }),
        )
            ->willReturn(new Envelope($domainEventMock))
        ;
        $this->outboxMessageHandler->__invoke($outboxMessageMock);
    }

    public function testFailsToDispatchDomainEvent(): void
    {
        $outboxMessageMock = $this->createMock(OutboxMessage::class);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to dispatch domain event');
        $this->outboxMessageHandler->__invoke($outboxMessageMock);
    }
}
