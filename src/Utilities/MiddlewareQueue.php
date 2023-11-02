<?php

declare(strict_types=1);

namespace Colossal\Routing\Utilities;

use Psr\Http\Message\{
    ResponseInterface,
    ServerRequestInterface
};
use Psr\Http\Server\{
    MiddlewareInterface,
    RequestHandlerInterface
};
use SplQueue;

final class MiddlewareQueue implements MiddlewareInterface
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->queue = new SplQueue();
    }

    /**
     * @see MiddlewareInterface::process()
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->queue->rewind();

        $runner = new MiddlewareQueueRunner($this, $handler);
        $result = $runner->handle($request);

        $this->queue->rewind();

        return $result;
    }

    /**
     * Delegates to underlying queue. @see SplQueue::valid().
     */
    public function valid(): bool
    {
        return $this->queue->valid();
    }

    /**
     * Delegates to underlying queue. @see SplQueue::current().
     */
    public function current(): MiddlewareInterface
    {
        return $this->queue->current();
    }

    /**
     * Delegates to underlying queue. @see SplQueue::next().
     */
    public function next(): void
    {
        $this->queue->next();
    }

    /**
     * Delegates to underlying queue. @see SplQueue::prev().
     */
    public function prev(): void
    {
        $this->queue->prev();
    }

    /**
     * Delegates to underlying queue. @see SplQueue::enqueue().
     */
    public function enqueue(MiddlewareInterface $middleware): void
    {
        $this->queue->enqueue($middleware);
    }

    /**
     * Delegates to underlying queue. @see SplQueue::dequeue().
     */
    public function dequeue(): MiddlewareInterface
    {
        return $this->queue->dequeue();
    }

    /**
     * @var \SplQueue<MiddlewareInterface> $queue The queue of middlewares.
     */
    private \SplQueue $queue;
}
