<?php

declare(strict_types=1);

namespace Lingoda\DomainEventsBundle\Tests\Infra\Doctrine\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\UnitOfWork;
use Lingoda\DomainEventsBundle\Domain\Model\ContainsEvents;
use Lingoda\DomainEventsBundle\Domain\Model\DomainEvent;
use Lingoda\DomainEventsBundle\Domain\Model\OutboxStore;
use Lingoda\DomainEventsBundle\Domain\Model\ReplaceableDomainEvent;
use Lingoda\DomainEventsBundle\Infra\Doctrine\EventSubscriber\PersistDomainEventsSubscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;

final class PersistDomainEventsSubscriberTest extends TestCase
{
    private MockObject $outboxStoreMock;
    private PersistDomainEventsSubscriber $persistDomainEventsSubscriber;

    protected function setUp(): void
    {
        $this->outboxStoreMock = $this->createMock(OutboxStore::class);
        $this->persistDomainEventsSubscriber = new PersistDomainEventsSubscriber($this->outboxStoreMock);
    }

    public function testCanPersistDomainEvents(): void
    {
        $entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $unitOfWorkMock = $this->createMock(UnitOfWork::class);
        $preFlushEventArgsMock = $this->createMock(PreFlushEventArgs::class);
        $insertedEntityMock = $this->createMock(ContainsEvents::class);
        $updatedEntityMock = $this->createMock(ContainsEvents::class);
        $deletedEntityMock = $this->createMock(ContainsEvents::class);
        $scheduledInsertEntityMock = $this->createMock(ContainsEvents::class);
        $domainEventMock = $this->createMock(DomainEvent::class);
        $replaceableDomainEventMock = $this->createMock(ReplaceableDomainEvent::class);

        $unitOfWorkMock->expects($this->once())->method('getIdentityMap')->willReturn([
            [$insertedEntityMock, new stdClass()],
            [$updatedEntityMock],
            [$deletedEntityMock],
        ]);
        $unitOfWorkMock->expects($this->once())->method('getScheduledEntityInsertions')->willReturn([
            123 => $scheduledInsertEntityMock,
        ]);
        $entityManagerMock->expects($this->once())->method('getUnitOfWork')->willReturn($unitOfWorkMock);
        $preFlushEventArgsMock->expects($this->once())->method('getObjectManager')->willReturn($entityManagerMock);
        $insertedEntityMock->expects($this->once())->method('clearRecordedEvents');
        $insertedEntityMock->expects($this->once())->method('getRecordedEvents')->willReturn(
            [$domainEventMock, $replaceableDomainEventMock],
        );
        $updatedEntityMock->expects($this->once())->method('clearRecordedEvents');
        $updatedEntityMock->expects($this->once())->method('getRecordedEvents')->willReturn([$domainEventMock]);
        $deletedEntityMock->expects($this->once())->method('clearRecordedEvents');
        $deletedEntityMock->expects($this->once())->method('getRecordedEvents')->willReturn([$domainEventMock]);
        $scheduledInsertEntityMock->expects($this->once())->method('clearRecordedEvents');
        $scheduledInsertEntityMock->expects($this->once())->method('getRecordedEvents')->willReturn([$domainEventMock]);
        $this->outboxStoreMock->expects($this->exactly(4))->method('append')->with($domainEventMock);
        $this->outboxStoreMock->expects($this->once())->method('replace')->with($replaceableDomainEventMock);
        $this->persistDomainEventsSubscriber->preFlush($preFlushEventArgsMock);
    }

    public function testCanPersistIdentifiedEntities(): void
    {
        $entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $unitOfWorkMock = $this->createMock(UnitOfWork::class);
        $preFlushEventArgsMock = $this->createMock(PreFlushEventArgs::class);
        $updatedEntityMock = $this->createMock(ContainsEvents::class);
        $domainEventMock = $this->createMock(DomainEvent::class);
        $unitOfWorkMock->expects($this->once())->method('getIdentityMap')->willReturn([
            [$updatedEntityMock, new stdClass()],
        ]);
        $unitOfWorkMock->expects($this->once())->method('getScheduledEntityInsertions')->willReturn([]);
        $entityManagerMock->expects($this->once())->method('getUnitOfWork')->willReturn($unitOfWorkMock);
        $preFlushEventArgsMock->expects($this->once())->method('getObjectManager')->willReturn($entityManagerMock);
        $updatedEntityMock->expects($this->once())->method('clearRecordedEvents');
        $updatedEntityMock->expects($this->once())->method('getRecordedEvents')->willReturn([$domainEventMock]);
        $this->outboxStoreMock->expects($this->once())->method('append')->with($domainEventMock);
        $this->persistDomainEventsSubscriber->preFlush($preFlushEventArgsMock);
    }

    public function testCanPersistEntitiesScheduledForInsert(): void
    {
        $entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $unitOfWorkMock = $this->createMock(UnitOfWork::class);
        $preFlushEventArgsMock = $this->createMock(PreFlushEventArgs::class);
        $scheduledInsertEntityMock = $this->createMock(ContainsEvents::class);
        $domainEventMock = $this->createMock(DomainEvent::class);
        $unitOfWorkMock->expects($this->once())->method('getIdentityMap')->willReturn([[new stdClass()]]);
        $unitOfWorkMock->expects($this->once())->method('getScheduledEntityInsertions')->willReturn([
            123 => $scheduledInsertEntityMock,
        ]);
        $entityManagerMock->expects($this->once())->method('getUnitOfWork')->willReturn($unitOfWorkMock);
        $preFlushEventArgsMock->expects($this->once())->method('getObjectManager')->willReturn($entityManagerMock);
        $scheduledInsertEntityMock->expects($this->once())->method('clearRecordedEvents');
        $scheduledInsertEntityMock->expects($this->once())->method('getRecordedEvents')->willReturn([$domainEventMock]);
        $this->outboxStoreMock->expects($this->once())->method('append')->with($domainEventMock);
        $this->persistDomainEventsSubscriber->preFlush($preFlushEventArgsMock);
    }
}
