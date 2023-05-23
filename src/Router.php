<?php

declare(strict_types=1);

namespace Colossal\Routing;

use Colossal\Http\Message\Response;
use Colossal\Routing\Utilities\NullMiddleware;
use Psr\Http\Message\{
    ResponseInterface,
    ServerRequestInterface
};
use Psr\Http\Server\{
    MiddlewareInterface,
    RequestHandlerInterface
};

class Router implements RequestHandlerInterface
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->routes       = [];
        $this->middleware   = new NullMiddleware();
    }

    /**
     * @see RequestHandlerInterface::handle()
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        foreach ($this->routes as $route) {
            if ($route->matches($request)) {
                return $this->middleware->process($request, $route);
            }
        }

        return (new Response())->withStatus(404);
    }

    /**
     * Add a route using PCRE pattern matching to the router.
     * @param string $method    The HTTP method of the route.
     * @param string $pattern   The PCRE pattern of the route.
     * @param \Closure $closure The handler of the route.
     */
    public function addRoute(string $method, string $pattern, \Closure $handler): void
    {
        foreach ($this->routes as $route) {
            if ($route->getMethod() === $method && $route->getPattern() === $pattern) {
                throw new \RuntimeException("Route with method '$method', and pattern '$pattern' already exists.");
            }
        }

        array_push($this->routes, new Route($method, $pattern, $handler));
    }

    /**
     * Add a controller to the router using the reflection API.
     * 
     * All methods marked with the attribute #[Route(method: "<http-method>", pattern: "<pcre-pattern>")]
     * will be registered as individual routes (via addRoute) where:
     *      - <http-method>  (string) Is the HTTP method of the route.
     *      - <pcre-pattern> (string) Is the PCRE pattern of the route.
     *      - The method will be wrapped in a closure as the handler of the route.
     * 
     * @param string $controllerClassName The name of the controller class to register.
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
                if (is_null($routeHandler)) {
                    throw new \RuntimeException("Could not create route handler for controller '$controllerClassName'.");
                }
                $this->addRoute($routeMethod, $routePattern, $routeHandler);
            }
        }
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
     * @var array<Route> The routes for this router.
     */
    private array $routes;

    /**
     * @var MiddlewareInterface The middleware for this router.
     */
    private MiddlewareInterface $middleware;
}
