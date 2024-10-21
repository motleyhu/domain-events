<?php

declare(strict_types=1);

namespace Lingoda\DomainEventsBundle\Tests\Infra\Doctrine\Entity;

use Carbon\CarbonImmutable;
use Lingoda\DomainEventsBundle\Domain\Model\DomainEvent;
use Lingoda\DomainEventsBundle\Infra\Doctrine\Entity\OutboxRecord;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class OutboxRecordTest extends TestCase
{
    private MockObject $domainEventMock;
    private OutboxRecord $outboxRecord;

    protected function setUp(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::now());

        $this->domainEventMock = $this->createMock(DomainEvent::class);
        $this->outboxRecord = new OutboxRecord('entity-id', $this->domainEventMock, CarbonImmutable::now());
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
    }

    public function testInitializable(): void
    {
        $this->assertInstanceOf(OutboxRecord::class, $this->outboxRecord);
        $this->assertEquals('entity-id', $this->outboxRecord->getEntityId());
        $this->assertEquals($this->domainEventMock, $this->outboxRecord->getDomainEvent());
        $this->assertEquals(true, $this->outboxRecord->getOccurredAt()->eq(CarbonImmutable::now()));
        $this->assertNull($this->outboxRecord->getPublishedOn());
    }

    public function testCanBePublished(): void
    {
        $tomorrow = new CarbonImmutable('tomorrow');
        $this->outboxRecord->setPublishedOn($tomorrow);
        $this->assertTrue($this->outboxRecord->getPublishedOn()->eq($tomorrow));
    }
}
