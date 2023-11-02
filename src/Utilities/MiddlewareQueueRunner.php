<?php

declare(strict_types=1);

namespace Colossal\Routing\Utilities;

use Psr\Http\Message\{
    ResponseInterface,
    ServerRequestInterface
};
use Psr\Http\Server\RequestHandlerInterface;

class MiddlewareQueueRunner implements RequestHandlerInterface
{
    /**
     * Constructor.
     * @param MiddlewareQueue $queue            The middleware queue for this runner.
     * @param RequestHandlerInterface $handler  The final request handler for this runner.
     */
    public function __construct(MiddlewareQueue $queue, RequestHandlerInterface $handler)
    {
        $this->queue    = $queue;
        $this->handler  = $handler;
    }

    /**
     * @see RequestHandlerInterface::handle()
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->queue->valid()) {
            $current = $this->queue->current();
            $this->queue->next();
            return $current->process($request, $this);
        } else {
            return $this->handler->handle($request);
        }
    }

    /**
     * @var MiddlewareQueue $queue The middleware queue for this runner.
     */
    private MiddlewareQueue $queue;

    /**
     * @var RequestHandlerInterface $handler The final request handler for this runner.
     */
    private RequestHandlerInterface $handler;
}
