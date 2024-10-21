<?php

declare(strict_types=1);

namespace Lingoda\DomainEventsBundle\Tests\Infra\Doctrine;

use Carbon\CarbonImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\UnitOfWork;
use Lingoda\DomainEventsBundle\Domain\Model\DomainEvent;
use Lingoda\DomainEventsBundle\Domain\Model\ReplaceableDomainEvent;
use Lingoda\DomainEventsBundle\Infra\Doctrine\DoctrineOutboxStore;
use Lingoda\DomainEventsBundle\Infra\Doctrine\Entity\OutboxRecord;
use Lingoda\DomainEventsBundle\Infra\Doctrine\Event\PreAppendEvent;
use Lingoda\DomainEventsBundle\Infra\Doctrine\Repository\OutboxRecordRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class DoctrineOutboxStoreTest extends TestCase
{
    private MockObject $entityManagerMock;
    private MockObject $eventDispatcherMock;
    private DoctrineOutboxStore $doctrineOutboxStore;

    protected function setUp(): void
    {
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->doctrineOutboxStore = new DoctrineOutboxStore($this->entityManagerMock, $this->eventDispatcherMock);
    }

    public function testCanAppendDomainEvents(): void
    {
        $domainEventMock = $this->createMock(DomainEvent::class);
        $unitOfWorkMock = $this->createMock(UnitOfWork::class);
        $now = CarbonImmutable::now();
        $domainEventMock->expects($this->once())->method('getEntityId')->willReturn('entity-id');
        $domainEventMock->expects($this->once())->method('getOccurredAt')->willReturn($now);
        $this->eventDispatcherMock->expects($this->once())->method('dispatch')->with(
            $this->isInstanceOf(PreAppendEvent::class),
        );
        $this->entityManagerMock->expects($this->once())->method('getUnitOfWork')->willReturn($unitOfWorkMock);
        $this->entityManagerMock->expects($this->once())->method('persist')->with(
            $this->callback(
                static fn (OutboxRecord $outboxRecord): bool => $outboxRecord->getEntityId() === 'entity-id' && $outboxRecord->getPublishedOn() === null,
            ),
        );
        $this->doctrineOutboxStore->append($domainEventMock);
    }

    public function testCanReplaceDomainEvent(): void
    {
        $domainEventMock = $this->createMock(DomainEvent::class);
        $replaceableDomainEventMock = $this->createMock(ReplaceableDomainEvent::class);
        $unitOfWorkMock = $this->createMock(UnitOfWork::class);
        $repositoryMock = $this->createMock(EntityRepository::class);
        $outboxRecordMock = $this->createMock(OutboxRecord::class);
        // append call
        $now = CarbonImmutable::now();
        $domainEventMock->expects($this->once())->method('getEntityId')->willReturn('entity-id');
        $domainEventMock->expects($this->once())->method('getOccurredAt')->willReturn($now);
        $this->eventDispatcherMock->expects($this->exactly(2))->method('dispatch')->with(
            $this->isInstanceOf(PreAppendEvent::class),
        );
        $this->entityManagerMock->expects($this->once())->method('getUnitOfWork')->willReturn($unitOfWorkMock);
        $this->entityManagerMock->expects($this->exactly(2))->method('persist')->with(
            $this->callback(
                static fn (OutboxRecord $outboxRecordMock): bool => $outboxRecordMock->getPublishedOn() === null,
            ),
        );
        // replace call
        $replaceableDomainEventMock->expects($this->once())->method('getEntityId')->willReturn('replaceable-entity-id');
        $replaceableDomainEventMock->expects($this->once())->method('getOccurredAt')->willReturn($now);
        $this->entityManagerMock->expects($this->once())->method('getRepository')->with(
            OutboxRecord::class,
        )->willReturn($repositoryMock);
        $repositoryMock->expects($this->once())->method('findBy')->with([
            'entityId' => 'replaceable-entity-id',
            'eventType' => $replaceableDomainEventMock::class,
            'publishedOn' => null,
        ])
            ->willReturn([$outboxRecordMock])
        ;
        $this->entityManagerMock->expects($this->once())->method('remove')->with($outboxRecordMock);
        // remove stored events that needs replacement
        $this->doctrineOutboxStore->replace($domainEventMock);
        $this->doctrineOutboxStore->replace($replaceableDomainEventMock);
    }

    public function testCanPublishStoredEvent(): void
    {
        $storedEventMock = $this->createMock(OutboxRecord::class);
        $this->entityManagerMock->expects($this->once())->method('persist')->with($storedEventMock);
        $this->entityManagerMock->expects($this->once())->method('flush');
        $this->doctrineOutboxStore->publish($storedEventMock);
    }

    public function testCanPurgeAllPublishedEvents(): void
    {
        $outboxRecordRepoMock = $this->createMock(OutboxRecordRepository::class);
        $this->entityManagerMock->expects($this->once())->method('getRepository')->with(
            OutboxRecord::class,
        )->willReturn($outboxRecordRepoMock);
        $this->entityManagerMock->expects($this->once())->method('getRepository')->with(OutboxRecord::class);
        $outboxRecordRepoMock->expects($this->once())->method('purgePublishedEvents');
        $this->doctrineOutboxStore->purgePublishedEvents();
    }
}
