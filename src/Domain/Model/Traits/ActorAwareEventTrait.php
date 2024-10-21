<?php

declare(strict_types=1);

namespace Lingoda\DomainEventsBundle\Domain\Model\Traits;

// @phpstan-ignore-next-line Only used in userland
trait ActorAwareEventTrait
{
    private ?int $actorId = null;

    public function getActorId(): ?int
    {
        return $this->actorId;
    }

    public function setActorId(?int $actorId = null): void
    {
        $this->actorId = $actorId;
    }

    public function hasActorId(): bool
    {
        return (bool) $this->actorId;
    }
}
