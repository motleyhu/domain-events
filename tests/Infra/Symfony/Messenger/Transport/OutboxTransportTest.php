<?php

declare(strict_types=1);

namespace Lingoda\DomainEventsBundle\Tests\Infra\Symfony\Messenger\Transport;

use Carbon\CarbonImmutable;
use Doctrine\DBAL\Driver\PDO\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Lingoda\DomainEventsBundle\Domain\Model\DomainEvent;
use Lingoda\DomainEventsBundle\Infra\Doctrine\Entity\OutboxRecord;
use Lingoda\DomainEventsBundle\Infra\Doctrine\Repository\OutboxRecordRepository;
use Lingoda\DomainEventsBundle\Infra\Symfony\Messenger\OutboxMessage;
use Lingoda\DomainEventsBundle\Infra\Symfony\Messenger\Transport\OutboxReceivedStamp;
use Lingoda\DomainEventsBundle\Infra\Symfony\Messenger\Transport\OutboxTransport;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;

final class OutboxTransportTest extends TestCase
{
    private MockObject $entityManagerMock;
    private MockObject $outboxRecordRepositoryMock;
    private OutboxTransport $outboxTransport;

    protected function setUp(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::now());

        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->outboxRecordRepositoryMock = $this->createMock(OutboxRecordRepository::class);

        $this->outboxTransport = new OutboxTransport($this->entityManagerMock);
        $this->entityManagerMock->expects($this->once())->method('getRepository')->with(
            OutboxRecord::class,
        )->willReturn($this->outboxRecordRepositoryMock);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
    }

    public function testThrowsExceptionOnSend(): void
    {
        $this->expectException(TransportException::class);
        $this->outboxTransport->send(new Envelope(new stdClass()));
    }

    public function testCanRejectAndAck(): void
    {
        $domainEventMock = $this->createMock(DomainEvent::class);
        $this->entityManagerMock->expects($this->once())->method('beginTransaction');
        $this->entityManagerMock->expects($this->once())->method('commit');
        $envelope = Envelope::wrap($domainEventMock)
            ->with(new OutboxReceivedStamp(1))
        ;
        $this->outboxRecordRepositoryMock->expects($this->once())->method('deleteRecord')->with(1);
        $this->outboxTransport->reject($envelope);
        $this->outboxTransport->ack($envelope);
    }

    public function testThrowsExceptionIfStampMissingDuringRejectAndAck(): void
    {
        $domainEventMock = $this->createMock(DomainEvent::class);
        $this->entityManagerMock->expects($this->once())->method('beginTransaction');
        $this->entityManagerMock->expects($this->once())->method('rollback');
        $this->outboxRecordRepositoryMock->expects($this->never())->method('deleteRecord')->with($this->any());
        $envelope = Envelope::wrap($domainEventMock);
        $expectedException = new TransportException('No OutboxReceivedStamp found on the Envelope.');
        $this->expectExceptionObject($expectedException);
        $this->outboxTransport->reject($envelope);
        /** should not throw exception */
        $this->outboxTransport->ack($envelope);
    }

    public function testThrowsTransportExceptionDuringDbErrorDuringRejectAndAck(): void
    {
        $domainEventMock = $this->createMock(DomainEvent::class);
        $this->entityManagerMock->expects($this->once())->method('beginTransaction');
        $this->entityManagerMock->expects($this->once())->method('rollback');
        $this->outboxRecordRepositoryMock->expects($this->once())->method('deleteRecord')->with(
            $this->any(),
        )->willThrowException(new Exception('DBAL error'));
        $envelope = Envelope::wrap($domainEventMock)
            ->with(new OutboxReceivedStamp(1))
        ;
        $this->expectException(TransportException::class);
        $this->outboxTransport->reject($envelope);
        /** should not throw exception */
        $this->outboxTransport->ack($envelope);
    }

    public function testCanGetRecord(): void
    {
        $outboxRecordMock = $this->createMock(OutboxRecord::class);
        $domainEventMock = $this->createMock(DomainEvent::class);
        $this->entityManagerMock->expects($this->exactly(2))->method('beginTransaction');
        $this->entityManagerMock->expects($this->exactly(2))->method('commit');
        $this->entityManagerMock->expects($this->exactly(2))->method('flush');
        $this->outboxRecordRepositoryMock->expects($this->once())->method('fetchNextRecordForUpdate')
            ->willReturn(null, $outboxRecordMock)
        ;
        // fetching empty database
        $this->assertEquals([], $this->outboxTransport->get());
        // fetching a record
        $outboxRecordMock->expects($this->once())->method('getId')->willReturn(1);
        $outboxRecordMock->expects($this->once())->method('getDomainEvent')->willReturn($domainEventMock);
        $outboxRecordMock->expects($this->once())->method('setPublishedOn')->willReturn(CarbonImmutable::now());
        $records = $this->outboxTransport->get();
        $this->assertCount(1, $records);
        $this->assertIsOutboxEnvelope($records[0], $outboxRecordMock);
    }

    private function assertIsOutboxEnvelope(mixed $subject, OutboxRecord $record): void
    {
        $this->assertInstanceOf(Envelope::class, $subject);

        $message = $subject->getMessage();
        $this->assertInstanceOf(OutboxMessage::class, $message);

        $this->assertSame($message->getDomainEvent(), $record->getDomainEvent());

        $outboxReceivedStamp = $subject->last(OutboxReceivedStamp::class);
        $this->assertInstanceOf(OutboxReceivedStamp::class, $outboxReceivedStamp);
        $this->assertSame($outboxReceivedStamp->getId(), $record->getId());

        $transportMessageIdStamp = $subject->last(TransportMessageIdStamp::class);
        $this->assertInstanceOf(TransportMessageIdStamp::class, $transportMessageIdStamp);
        $this->assertSame($transportMessageIdStamp->getId(), $record->getId());
    }
}
