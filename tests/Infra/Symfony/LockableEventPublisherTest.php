<?php

declare(strict_types=1);

namespace Lingoda\DomainEventsBundle\Tests\Infra\Symfony;

use Lingoda\DomainEventsBundle\Domain\Model\DomainEvent;
use Lingoda\DomainEventsBundle\Domain\Model\DomainEventDispatcher;
use Lingoda\DomainEventsBundle\Domain\Model\OutboxStore;
use Lingoda\DomainEventsBundle\Infra\Doctrine\Entity\OutboxRecord;
use Lingoda\DomainEventsBundle\Infra\Symfony\LockableEventPublisher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;

final class LockableEventPublisherTest extends TestCase
{
    private MockObject $domainEventDispatcherMock;
    private MockObject $outboxStoreMock;
    private MockObject $lockFactoryMock;
    private LockableEventPublisher $lockableEventPublisher;

    protected function setUp(): void
    {
        $this->domainEventDispatcherMock = $this->createMock(DomainEventDispatcher::class);
        $this->outboxStoreMock = $this->createMock(OutboxStore::class);
        $this->lockFactoryMock = $this->createMock(LockFactory::class);
        $this->lockableEventPublisher = new LockableEventPublisher(
            $this->domainEventDispatcherMock,
            $this->outboxStoreMock,
            $this->lockFactoryMock,
        );
    }

    public function testCanPublishDomainEvents(): void
    {
        $outboxRecordMock = $this->createMock(OutboxRecord::class);
        $domainEventMock = $this->createMock(DomainEvent::class);
        $lockMock = $this->createMock(SharedLockInterface::class);
        $outboxRecordMock->expects($this->once())->method('getId')->willReturn(1);
        $outboxRecordMock->expects($this->once())->method('getDomainEvent')->willReturn($domainEventMock);
        $this->outboxStoreMock->expects($this->once())->method('allUnpublished')->willReturn([$outboxRecordMock]);
        $this->lockFactoryMock->expects($this->exactly(2))->method('createLock')->with('outbox-record-1')
            ->willReturn($lockMock)
        ;
        $lockMock->expects($this->exactly(2))->method('acquire')->willReturn(true, false);
        $lockMock->expects($this->once())->method('release');
        $this->domainEventDispatcherMock->expects($this->once())->method('dispatch')->with($domainEventMock);
        $this->outboxStoreMock->expects($this->once())->method('publish')->with($outboxRecordMock);
        $this->lockableEventPublisher->publishDomainEvents();
        // without lock
        $this->lockableEventPublisher->publishDomainEvents();
        // already locked
    }
}
