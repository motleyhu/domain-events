<?php

declare(strict_types=1);

namespace Lingoda\DomainEventsBundle\Tests\Infra\Symfony\EventSubscriber;

use Exception;
use Lingoda\DomainEventsBundle\Domain\Model\DomainEvent;
use Lingoda\DomainEventsBundle\Infra\Symfony\EventSubscriber\DefaultDomainEventDispatcher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class DefaultDomainEventDispatcherTest extends TestCase
{
    private MockObject $messageBusMock;
    private DefaultDomainEventDispatcher $defaultDomainEventDispatcher;

    protected function setUp(): void
    {
        $this->messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->defaultDomainEventDispatcher = new DefaultDomainEventDispatcher($this->messageBusMock);
    }

    public function testCanDispatchDomainEvent(): void
    {
        $domainEventMock = $this->createMock(DomainEvent::class);
        $this->messageBusMock->expects($this->once())->method('dispatch')->with($domainEventMock)
            ->willReturn(new Envelope($domainEventMock))
        ;
        $this->defaultDomainEventDispatcher->dispatch($domainEventMock);
    }

    public function testFailsToDispatchDomainEvent(): void
    {
        $domainEventMock = $this->createMock(DomainEvent::class);
        $this->messageBusMock->expects($this->once())->method('dispatch')->with($domainEventMock)
            ->willThrowException(new Exception())
        ;
        $this->expectException(RuntimeException::class);
        $this->defaultDomainEventDispatcher->expectExceptionMessage('Failed to dispatch domain event');
        $this->defaultDomainEventDispatcher->dispatch($domainEventMock);
    }
}
