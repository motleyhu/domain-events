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
        $outboxMessage = new OutboxMessage($domainEventMock);
        $this->assertEquals($domainEventMock, $outboxMessage->getDomainEvent());
    }
}
