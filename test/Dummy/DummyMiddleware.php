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
     * @param null|MiddlewareInterface $next The next middleware to pass the request to.
     */
    public function __construct(string $name, null|MiddlewareInterface $next)
    {
        $this->name = $name;
        $this->next = $next;
    }

    /**
     * @see MiddlewareInterface::process()
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $request = $request->withAttribute($this->name, true);

        return !is_null($this->next)
            ? $this->next->process($request, $handler)
            : $handler->handle($request);
    }

    /**
     * @var string The name of this middleware.
     */
    public string $name;

    /**
     * @var null|MiddlewareInterface The next middleware to pass the request to.
     */
    public null|MiddlewareInterface $next;
}
