<?php

declare(strict_types=1);

namespace Colossal\Routing;

use Colossal\Http\Message\{
    Response,
    ServerRequest,
    Uri
};
use Colossal\Routing\Dummy\{
    DummyController,
    DummyMiddleware
};
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\{
    ResponseInterface,
    ServerRequestInterface
};

/**
 * @covers \Colossal\Routing\Router
 * @uses \Colossal\Routing\Route
 * @uses \Colossal\Routing\Utilities\Utilities
 */
class RouterTest extends TestCase
{
    public function testMiddlewareOnlyExecutesWhenMatchingRouteExists(): void
    {
        $router = new Router();
        $router->addRoute("GET", "%^/users$%", function (ServerRequestInterface $request): ResponseInterface {
            return (new Response())->withStatus(200);
        });
        $router->addRoute("GET", "%^/posts$%", function (ServerRequestInterface $request): ResponseInterface {
            return (new Response())->withStatus(200);
        });

        $dummyMiddleware = new DummyMiddleware();

        $router->setMiddleware($dummyMiddleware);

        $testCases = [
            ["http://localhost:8080/users", true],
            ["http://localhost:8080/users", true],
            ["http://localhost:8080/api", false],
        ];

        foreach ($testCases as $testCase) {
            $dummyMiddleware->wasExecuted = false;

            $router->processRequest($this->createServerRequest("GET", $testCase[0]));

            $this->assertEquals($testCase[1], $dummyMiddleware->wasExecuted);
        }
    }

    public function testProcessRequest(): void
    {
        $subRouterA = new Router();
        $subRouterA->setFixedStart("/posts");
        $subRouterA->addRoute("GET", "%^/?$%", function () use (&$routeName): ResponseInterface {
            $routeName = "posts";
            return (new Response())->withStatus(200);
        });
        $subRouterA->addRoute("GET", "%^/(?<id>\d+)/?$%", function (int $id) use (&$routeName): ResponseInterface {
            $routeName = "posts-$id";
            return (new Response())->withStatus(200);
        });

        $subRouterB = new Router();
        $subRouterB->setFixedStart("/users");
        $subRouterB->addRoute("GET", "%^/?$%", function () use (&$routeName): ResponseInterface {
            $routeName = "users";
            return (new Response())->withStatus(200);
        });
        $subRouterB->addRoute("GET", "%^/(?<id>\d+)/?$%", function (int $id) use (&$routeName): ResponseInterface {
            $routeName = "users-$id";
            return (new Response())->withStatus(200);
        });

        $router = new Router();
        $router->addSubRouter($subRouterA);
        $router->addSubRouter($subRouterB);
        $router->addRoute("GET", "%^/index/?$%", function () use (&$routeName): ResponseInterface {
            $routeName = "index";
            return (new Response())->withStatus(200);
        });

        $router->processRequest($this->createServerRequest("GET", "http://localhost:8080/posts"));
        $this->assertEquals("posts", $routeName);
        $router->processRequest($this->createServerRequest("GET", "http://localhost:8080/posts/1"));
        $this->assertEquals("posts-1", $routeName);
        $router->processRequest($this->createServerRequest("GET", "http://localhost:8080/users"));
        $this->assertEquals("users", $routeName);
        $router->processRequest($this->createServerRequest("GET", "http://localhost:8080/users/1"));
        $this->assertEquals("users-1", $routeName);
        $router->processRequest($this->createServerRequest("GET", "http://localhost:8080/index"));
        $this->assertEquals("index", $routeName);
        $this->assertEquals(404, $router->processRequest($this->createServerRequest("GET", "http://localhost:8080/dummy"))->getStatusCode());
    }

    public function testProcessRequestSubRouterPriorityIsInOrderOfDescendingFixedStartLength(): void
    {
        $routeName = "";

        $subRouterA = new Router();
        $subRouterA->setFixedStart("/");
        $subRouterA->addRoute("GET", "%^/users/?$%", function () use (&$routeName): ResponseInterface {
            $routeName = "A";
            return (new Response())->withStatus(200);
        });

        $subRouterB = new Router();
        $subRouterB->setFixedStart("/users/");
        $subRouterB->addRoute("GET", "%^/?$%", function () use (&$routeName): ResponseInterface {
            $routeName = "B";
            return (new Response())->withStatus(200);
        });

        $router = new Router();
        $router->addSubRouter($subRouterA);
        $router->addSubRouter($subRouterB);

        $request = $this->createServerRequest("GET", "http://localhost:8080/users");

        $this->assertEquals(200, $subRouterA->processRequest($request)->getStatusCode());
        $this->assertEquals(200, $subRouterB->processRequest($request)->getStatusCode());
        $router->processRequest($request);
        $this->assertEquals("B", $routeName);
    }

    public function testProcessRequestRoutePriorityIsInOrderAdded(): void
    {
        $routeName = "";

        $router = new Router();
        $router->addRoute("GET", "%^/users/(?<id>1|2)$%", function (int $id) use (&$routeName): ResponseInterface {
            $routeName = "A";
            return (new Response())->withStatus(200);
        });
        $router->addRoute("GET", "%^/users/(?<id>2|3)$%", function (int $id) use (&$routeName): ResponseInterface {
            $routeName = "B";
            return (new Response())->withStatus(200);
        });
        $router->addRoute("GET", "%^/users/(?<id>3|4)$%", function (int $id) use (&$routeName): ResponseInterface {
            $routeName = "C";
            return (new Response())->withStatus(200);
        });

        $router->processRequest($this->createServerRequest("GET", "http://localhost:8080/users/2"));
        $this->assertEquals("A", $routeName);
        $router->processRequest($this->createServerRequest("GET", "http://localhost:8080/users/3"));
        $this->assertEquals("B", $routeName);
    }

    public function testProcessRequestReturns404IfNoMatchingRouteExists(): void
    {
        $router = new Router();

        $response = $router->processRequest($this->createServerRequest("GET", "http://localhost:8080/users/2"));

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testAddSubRouterThrowsIfSubRouterAddedTwice(): void
    {
        $fixedStart = "/users/";

        $subRouterA = new Router();
        $subRouterA->setFixedStart($fixedStart);

        $subRouterB = new Router();
        $subRouterB->setFixedStart($fixedStart);

        $router = new Router();

        $this->expectExceptionMessage("Sub-Router with fixed start '/users' already exists.");
        $router->addSubRouter($subRouterA);
        $router->addSubRouter($subRouterB);
    }

    public function testAddRouteThrowsIfRouteAddedTwice(): void
    {
        $handler = function (): ResponseInterface {
            return (new Response())->withStatus(200);
        };

        $router = new Router();

        $this->expectExceptionMessage("Route with method 'GET', and pattern '%^/users$%' already exists.");
        $router->addRoute("GET", "%^/users$%", $handler);
        $router->addRoute("GET", "%^/users$%", $handler);
    }

    public function testAddController(): void
    {
        $router = new Router();
        $router->addController(DummyController::class);

        $user0Response = $router->processRequest($this->createServerRequest("GET", "http://localhost:8080/users/0"));
        $this->assertEquals(DummyController::USERS[0], json_decode($user0Response->getBody()->getContents(), associative: true));

        $user1Response = $router->processRequest($this->createServerRequest("GET", "http://localhost:8080/users/1"));
        $this->assertEquals(DummyController::USERS[1], json_decode($user1Response->getBody()->getContents(), associative: true));
    }

    private function createServerRequest(string $method, string $uri): ServerRequestInterface
    {
        return (new ServerRequest())
            ->withMethod($method)
            ->withUri(Uri::createUriFromString($uri));
    }
}
