<?php

declare(strict_types=1);

namespace Colossal\Routing;

use Colossal\Http\Message\Response;
use Colossal\Routing\Utilities\{
    NullMiddleware,
    Utilities
};
use Psr\Http\Message\{
    ResponseInterface,
    ServerRequestInterface
};
use Psr\Http\Server\{
    MiddlewareInterface,
    RequestHandlerInterface
};
use RuntimeException;

class Router implements RequestHandlerInterface
{
    private const COLOSSAL_ROUTING_PATH_ATTR = "CABD1AEE-5684-4FD4-BE5C-489717F45E99";

    /**
     * Get the server request routing path.
     *
     * This is either:
     *      - The value specified in the attribute with name self::COLOSAL_ROUTER_PATH_ATTR if it exists.
     *      - Otherwise, it will default to the path component of the server request URI.
     *
     * @param ServerRequestInterface $request The server request to get the routing path for.
     * @return string The server request routing path.
     */
    public static function getServerRequestRoutingPath(ServerRequestInterface $request): string
    {
        /** @phpstan-ignore-next-line - Attribute is assumed to be of type string. */
        return $request->getAttribute(self::COLOSSAL_ROUTING_PATH_ATTR, $request->getUri()->getPath());
    }

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware   = new NullMiddleware();
        $this->fixedStart   = "";
        $this->subRouters   = [];
        $this->routes       = [];
    }

    /**
     * Get whether the router matches the server request routing path.
     * @param ServerRequestInterface $request The server request to check.
     * @return bool Whether the router mathces $request's routing path.
     */
    public function matches(ServerRequestInterface $request): bool
    {
        return str_starts_with(self::getServerRequestRoutingPath($request), $this->fixedStart);
    }

    /**
     * @see RequestHandlerInterface::handle()
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $request = $request->withAttribute(
            self::COLOSSAL_ROUTING_PATH_ATTR,
            Utilities::strRemovePrefix(
                self::getServerRequestRoutingPath($request),
                $this->fixedStart
            )
        );

        foreach ($this->subRouters as $subRouter) {
            if ($subRouter->matches($request)) {
                return $this->middleware->process($request, $subRouter);
            }
        }

        foreach ($this->routes as $route) {
            if ($route->matches($request)) {
                return $this->middleware->process($request, $route);
            }
        }

        return (new Response())->withStatus(404);
    }

    /**
     * Set the middleware for this router.
     * @param MiddlewareInterface $middleware The middleware for this router.
     */
    public function setMiddleware(MiddlewareInterface $middleware): void
    {
        $this->middleware = $middleware;
    }

    /**
     * Set the fixed start for this router.
     * @param string $fixedStart The fixed start for this router's paths.
     */
    public function setFixedStart(string $fixedStart): void
    {
        $this->fixedStart = rtrim($fixedStart, "/");
    }

    /**
     * Add a sub-router to this router.
     * @param Router $subRouter The sub-router to add.
     */
    public function addSubRouter(Router $subRouter): void
    {
        foreach ($this->subRouters as $existingSubRouter) {
            if ($subRouter->fixedStart === $existingSubRouter->fixedStart) {
                throw new \RuntimeException("Sub-Router with fixed start '$subRouter->fixedStart' already exists.");
            }
        }

        array_push($this->subRouters, $subRouter);
        usort(
            $this->subRouters,
            function (Router $subRouterA, Router $subRouterB): int {
                return (strlen($subRouterA->fixedStart) >= strlen($subRouterB->fixedStart) ? -1 : +1);
            }
        );
    }

    /**
     * Add a route to the router.
     * @param string $method    The HTTP method  of the route.
     * @param string $pattern   The PCRE pattern of the route.
     * @param \Closure $handler The handler of the route.
     */
    public function addRoute(string $method, string $pattern, \Closure $handler): void
    {
        foreach ($this->routes as $existingRoute) {
            if ($method === $existingRoute->getMethod() && $pattern === $existingRoute->getPattern()) {
                throw new \RuntimeException("Route with method '$method', and pattern '$pattern' already exists.");
            }
        }

        array_push($this->routes, new Route($method, $pattern, $handler));
    }

    /**
     * Add all routes for a controller to the router.
     *
     * All methods marked with the attribute #[Route(method: "<http-method>", pattern: "<pcre-pattern>")]
     * will be registered as individual routes (via addRoute) where:
     *      - <http-method>  (string) Is the HTTP method  of the route.
     *      - <pcre-pattern> (string) Is the PCRE pattern of the route.
     *      - The method will be wrapped in a closure as the handler of the route.
     *
     * @param class-string $controllerClassName The name of the controller class to register.
     * @throws \ReflectionException If the calls to the reflection API fail.
     * @throws \RuntimeException    If the handler closure can not be created.
     */
    public function addController(string $controllerClassName): void
    {
        $reflectionClass = new \ReflectionClass($controllerClassName);
        foreach ($reflectionClass->getMethods() as $reflectionMethod) {
            foreach ($reflectionMethod->getAttributes(Route::class) as $routeAttribute) {
                $routeMethod  = $routeAttribute->getArguments()['method'];
                $routePattern = $routeAttribute->getArguments()['pattern'];
                $routeHandler = $reflectionMethod->getClosure($reflectionClass->newInstance());
                /** @phpstan-ignore-next-line - PHP documentation indicates that ReflectionMethod::getClosure() is not null. */
                $this->addRoute($routeMethod, $routePattern, $routeHandler);
            }
        }
    }

    /**
     * @var MiddlewareInterface The middleware for this router.
     */
    private MiddlewareInterface $middleware;

    /**
     * @var string The fixed start for this router's paths.
     */
    private string $fixedStart;

    /**
     * @var array<Router> The sub-routers for this router.
     */
    private array $subRouters;

    /**
     * @var array<Route> The routes for this router.
     */
    private array $routes;
}
