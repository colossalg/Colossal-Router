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
     * Constructor.
     * @param string $name The name of this middleware.
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * @see MiddlewareInterface::process()
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $request = $request->withAttribute($this->name, true);

        return $handler->handle($request);
    }

    /**
     * @var string The name of this middleware.
     */
    public string $name;
}
