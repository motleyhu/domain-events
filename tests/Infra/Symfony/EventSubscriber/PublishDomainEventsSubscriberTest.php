<?php

declare(strict_types=1);

namespace Lingoda\DomainEventsBundle\Tests\Infra\Symfony\EventSubscriber;

use Lingoda\DomainEventsBundle\Domain\Model\EventPublisher;
use Lingoda\DomainEventsBundle\Infra\Symfony\EventSubscriber\PublishDomainEventsSubscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;

final class PublishDomainEventsSubscriberTest extends TestCase
{
    private MockObject $eventPublisherMock;
    private PublishDomainEventsSubscriber $publishDomainEventsSubscriber;

    protected function setUp(): void
    {
        $this->eventPublisherMock = $this->createMock(EventPublisher::class);
        $this->publishDomainEventsSubscriber = new PublishDomainEventsSubscriber($this->eventPublisherMock, true);
    }

    public function testInitializable(): void
    {
        $this->assertInstanceOf(PublishDomainEventsSubscriber::class, $this->publishDomainEventsSubscriber);
        $this::getSubscribedEvents()->shouldIterateLike([
            KernelEvents::TERMINATE => 'publishEventsFromHttp',
            ConsoleEvents::TERMINATE => 'publishEventsFromConsole',
            WorkerMessageHandledEvent::class => 'publishEventsFromWorker',
        ]);
    }

    public function testPublishEventOnHttpTermination(): void
    {
        $kernelMock = $this->createMock(HttpKernelInterface::class);
        $this->eventPublisherMock->expects($this->once())->method('publishDomainEvents');
        $this->publishDomainEventsSubscriber->publishEventsFromHttp(new TerminateEvent(
            $kernelMock,
            Request::createFromGlobals(),
            new Response(),
        ));
    }

    public function testPublishEventOnConsoleTermination(): void
    {
        $commandMock = $this->createMock(Command::class);
        $inputMock = $this->createMock(InputInterface::class);
        $outputMock = $this->createMock(OutputInterface::class);
        $this->eventPublisherMock->expects($this->once())->method('publishDomainEvents');
        $this->publishDomainEventsSubscriber->publishEventsFromConsole(new ConsoleTerminateEvent(
            $commandMock,
            $inputMock,
            $outputMock,
            1,
        ));
    }

    public function testPublishEventOnMessageHandling(): void
    {
        $this->eventPublisherMock->expects($this->once())->method('publishDomainEvents');
        $this->publishDomainEventsSubscriber->publishEventsFromWorker(new WorkerMessageHandledEvent(
            new Envelope(new stdClass()),
            'receiver-name',
        ));
    }

    public function testWontPublishEventOnHttpTermination(): void
    {
        $kernelMock = $this->createMock(HttpKernelInterface::class);
        $this->publishDomainEventsSubscriber = new PublishDomainEventsSubscriber($this->eventPublisherMock, false);
        $this->eventPublisherMock->expects($this->never())->method('publishDomainEvents');
        $this->publishDomainEventsSubscriber->publishEventsFromHttp(new TerminateEvent(
            $kernelMock,
            Request::createFromGlobals(),
            new Response(),
        ));
    }

    public function testNotPublishEventOnConsoleTermination(): void
    {
        $commandMock = $this->createMock(Command::class);
        $inputMock = $this->createMock(InputInterface::class);
        $outputMock = $this->createMock(OutputInterface::class);
        $this->publishDomainEventsSubscriber = new PublishDomainEventsSubscriber($this->eventPublisherMock, false);
        $this->eventPublisherMock->expects($this->never())->method('publishDomainEvents');
        $this->publishDomainEventsSubscriber->publishEventsFromConsole(new ConsoleTerminateEvent(
            $commandMock,
            $inputMock,
            $outputMock,
            1,
        ));
    }

    public function testWontPublishEventOnMessageHandling(): void
    {
        $this->publishDomainEventsSubscriber = new PublishDomainEventsSubscriber($this->eventPublisherMock, false);
        $this->eventPublisherMock->expects($this->never())->method('publishDomainEvents');
        $this->publishDomainEventsSubscriber->publishEventsFromWorker(new WorkerMessageHandledEvent(
            new Envelope(new stdClass()),
            'receiver-name',
        ));
    }
}
