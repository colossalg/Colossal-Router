<?php

declare(strict_types=1);

namespace Colossal\Routing\Dummy;

use Psr\Http\Message\{
    ResponseInterface,
    ServerRequestInterface
};
use Psr\Http\Server\{
    MiddlewareInterface,
    RequestHandlerInterface
};

class DummyMiddleware implements MiddlewareInterface
{
    /**
     * @see MiddlewareInterface::process()
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->wasExecuted = true;

        return $handler->handle($request);
    }

    /**
     * @var bool Whether this middleware was executed.
     */
    public bool $wasExecuted = false;
}
