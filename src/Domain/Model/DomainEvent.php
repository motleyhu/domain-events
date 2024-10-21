<?php

declare(strict_types=1);

namespace Lingoda\DomainEventsBundle\Domain\Model;

use Carbon\CarbonImmutable;
use Stringable;

interface DomainEvent
{
    public function getEntityId(): Stringable;

    public function getOccurredAt(): CarbonImmutable;
}
