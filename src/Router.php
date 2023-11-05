<?php

declare(strict_types=1);

namespace Colossal\Routing;

use Colossal\Http\Message\Response;
use Colossal\MiddlewareQueue\MiddlewareQueue;
use Colossal\Routing\Utilities\Utilities;
use Psr\Http\Message\{
    ResponseInterface,
    ServerRequestInterface
};
use Psr\Http\Server\MiddlewareInterface;
use RuntimeException;

class Router
{
    public const COLOSSAL_REQUEST_ROUTE_MATCH_PATH_ATTR = "COLOSSAL_REQUEST_ROUTE_MATCH_PATH_ATTR";

    public const COLOSSAL_REQUEST_MIDDLEWARE_QUEUE_ATTR = "COLOSSAL_REQUEST_MIDDLEWARE_QUEUE_ATTR";

    /**
     * Get the server request route match path.
     *
     * This is either:
     *      - The value specified in the attribute with name self::COLOSSAL_REQUEST_ROUTE_MATCH_PATH_ATTR if it exists.
     *      - Otherwise, it will default to the path component of the server request URI.
     *
     * @param ServerRequestInterface $request The server request to get the route match path for.
     * @return string The server request route match path.
     */
    public static function getServerRequestRouteMatchPath(ServerRequestInterface $request): string
    {
        /** @phpstan-ignore-next-line - Attribute is assumed to be of type string. */
        return $request->getAttribute(
            self::COLOSSAL_REQUEST_ROUTE_MATCH_PATH_ATTR,
            $request->getUri()->getPath()
        );
    }

    /**
     * Get the server request middleware queue.
     *
     * This is either:
     *      - The value specified in the attribute with name self::COLOSSAL_REQUEST_MIDDLEWARE_QUEUE_ATTR if it exist.
     *      - Otherwise, it will default to a default constructed MiddlewareQueue.
     *
     * @param ServerRequestInterface $request The server request to get the middleware queue for.
     * @return MiddlewareQueue The server request middleware queue.
     */
    public static function getServerRequestMiddlewareQueue(ServerRequestInterface $request): MiddlewareQueue
    {
        /** @phpstan-ignore-next-line - Attribute is assumed to be of type MiddlewareQueue. */
        return $request->getAttribute(
            self::COLOSSAL_REQUEST_MIDDLEWARE_QUEUE_ATTR,
            new MiddlewareQueue()
        );
    }

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware   = null;
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
        return str_starts_with(self::getServerRequestRouteMatchPath($request), $this->fixedStart);
    }

    /**
     * Process a request.
     *
     * This does the following:
     *      - Updates the request's route match path and middleware queue.
     *      - Finds a matching sub-router or route and calls processRequest() on that (returning the result).
     *      - If no matching sub-router or route exists return a 404 response.
     *
     * @param ServerRequestInterface $request The server request to process.
     * @return ResponseInterface The response resulting from processing the request.
     */
    public function processRequest(ServerRequestInterface $request): ResponseInterface
    {
        $request = $this->getRequestWithUpdatedRouteMatchPath($request);
        $request = $this->getRequestWithUpdatedMiddlewareQueue($request);

        foreach ([...$this->subRouters, ...$this->routes] as $routeOrRouter) {
            if ($routeOrRouter->matches($request)) {
                return $routeOrRouter->processRequest($request);
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
     * @param null|MiddlewareInterface $middleware The middleware of the route.
     */
    public function addRoute(
        string $method,
        string $pattern,
        \Closure $handler,
        null|MiddlewareInterface $middleware = null
    ): void {
        foreach ($this->routes as $existingRoute) {
            if ($method === $existingRoute->getMethod() && $pattern === $existingRoute->getPattern()) {
                throw new \RuntimeException("Route with method '$method', and pattern '$pattern' already exists.");
            }
        }

        array_push($this->routes, new Route($method, $pattern, $handler, $middleware));
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
     * @param class-string $controllerClassName     The name of the controller class to register.
     * @param null|MiddlewareInterface $middleware  The middleware to register for all controller routes.
     * @throws \ReflectionException If the calls to the reflection API fail.
     * @throws \RuntimeException    If the handler closure can not be created.
     */
    public function addController(string $controllerClassName, null|MiddlewareInterface $middleware = null): void
    {
        $reflectionClass = new \ReflectionClass($controllerClassName);
        foreach ($reflectionClass->getMethods() as $reflectionMethod) {
            foreach ($reflectionMethod->getAttributes(Route::class) as $routeAttribute) {
                $routeMethod  = $routeAttribute->getArguments()['method'];
                $routePattern = $routeAttribute->getArguments()['pattern'];
                $routeHandler = $reflectionMethod->getClosure($reflectionClass->newInstance());
                /** @phpstan-ignore-next-line - PHP documentation indicates that ReflectionMethod::getClosure() is not null. */
                $this->addRoute($routeMethod, $routePattern, $routeHandler, $middleware);
            }
        }
    }

    /**
     * Return a new request with this router's fixed start stripped from the route match path.
     */
    private function getRequestWithUpdatedRouteMatchPath(ServerRequestInterface $request): ServerRequestInterface
    {
        $newRouteMatchPath = Utilities::strRemovePrefix(
            self::getServerRequestRouteMatchPath($request),
            $this->fixedStart
        );

        return $request->withAttribute(self::COLOSSAL_REQUEST_ROUTE_MATCH_PATH_ATTR, $newRouteMatchPath);
    }

    /**
     * Return a new request with this router's middleware enqueued on to the middleware queue.
     */
    private function getRequestWithUpdatedMiddlewareQueue(ServerRequestInterface $request): ServerRequestInterface
    {
        $newMiddlewareQueue = clone self::getServerRequestMiddlewareQueue($request);
        if (!is_null($this->middleware)) {
            $newMiddlewareQueue->enqueue($this->middleware);
        }

        return $request->withAttribute(self::COLOSSAL_REQUEST_MIDDLEWARE_QUEUE_ATTR, $newMiddlewareQueue);
    }

    /**
     * @var null|MiddlewareInterface The middleware for this router.
     */
    private null|MiddlewareInterface $middleware;

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
