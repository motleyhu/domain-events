<?php

declare(strict_types=1);

namespace Lingoda\DomainEventsBundle\Tests\Infra\Symfony\Messenger;

use Lingoda\DomainEventsBundle\Domain\Model\DomainEvent;
use Lingoda\DomainEventsBundle\Infra\Symfony\Messenger\OutboxMessage;
use PHPUnit\Framework\TestCase;

final class OutboxMessageTest extends TestCase
{
    public function testInitializable(): void
    {
        $domainEventMock = $this->createMock(DomainEvent::class);
        $this->outboxMessage = new OutboxMessage($domainEventMock);
        $this->assertInstanceOf(OutboxMessage::class, $this->outboxMessage);
        $this->assertEquals($domainEventMock, $this->outboxMessage->getDomainEvent());
    }
}
