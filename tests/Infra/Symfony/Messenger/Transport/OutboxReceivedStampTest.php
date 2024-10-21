<?php

declare(strict_types=1);

namespace Lingoda\DomainEventsBundle\Tests\Infra\Symfony\Messenger\Transport;

use Lingoda\DomainEventsBundle\Infra\Symfony\Messenger\Transport\OutboxReceivedStamp;
use PHPUnit\Framework\TestCase;

final class OutboxReceivedStampTest extends TestCase
{
    public function testInitializable(): void
    {
        $outboxReceivedStamp = new OutboxReceivedStamp(10);
        $this->assertInstanceOf(OutboxReceivedStamp::class, $outboxReceivedStamp);
        $this->assertEquals(10, $outboxReceivedStamp->getId());
    }
}
