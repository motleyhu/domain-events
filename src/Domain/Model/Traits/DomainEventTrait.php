<?php

declare(strict_types=1);

namespace Lingoda\DomainEventsBundle\Domain\Model\Traits;

use Carbon\CarbonImmutable;
use Stringable;

// @phpstan-ignore-next-line Only used in userland
trait DomainEventTrait
{
    private readonly Stringable $entityId;
    private readonly CarbonImmutable $occurredAt;

    public function getEntityId(): Stringable
    {
        return $this->entityId;
    }

    public function getOccurredAt(): CarbonImmutable
    {
        return $this->occurredAt;
    }

    protected function init(Stringable $entityId): void
    {
        $this->entityId = $entityId;
        $this->occurredAt = CarbonImmutable::now();
    }
}
