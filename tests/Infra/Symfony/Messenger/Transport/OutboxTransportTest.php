<?php

declare(strict_types=1);

namespace Lingoda\DomainEventsBundle\Tests\Infra\Symfony\Messenger\Transport;

use Carbon\CarbonImmutable;
use Doctrine\DBAL\Exception as DBALException;
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
        $this->expectException($expectedException);
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
        )->willThrowException(DBALException::class);
        $envelope = Envelope::wrap($domainEventMock)
            ->with(new OutboxReceivedStamp(1))
        ;
        $this->expectException(TransportException::class);
        $this->outboxTransport->expectExceptionMessage('');
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
        $outboxRecordMock->expects($this->once())->method('setPublishedOn')->willReturn(
            fn (CarbonImmutable $now) => $now->expects($this->once())->method('eq')->with(CarbonImmutable::now()),
        );
        $records = $this->outboxTransport->get();
        $this->assertCount(1, $records);
        $records[0]->shouldBeOutboxEnvelope($outboxRecordMock);
    }

    /**
     * @return array<string, callable>
     */
    public function getMatchers(): array
    {
        return [
            'beOutboxEnvelope' => static function ($subject, $record) {
                if (!$subject instanceof Envelope) {
                    return false;
                }

                $message = $subject->getMessage();
                if (!$message instanceof OutboxMessage) {
                    return false;
                }

                if ($message->getDomainEvent() !== $record->getDomainEvent()) {
                    return false;
                }

                $outboxReceivedStamp = $subject->last(OutboxReceivedStamp::class);
                if ($outboxReceivedStamp === null || $outboxReceivedStamp->getId() !== $record->getId()) {
                    return false;
                }

                $transportMessageIdStamp = $subject->last(TransportMessageIdStamp::class);
                if ($transportMessageIdStamp === null || $transportMessageIdStamp->getId() !== $record->getId()) {
                    return false;
                }

                return true;
            },
        ];
    }
}
