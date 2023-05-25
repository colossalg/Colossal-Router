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
 * @uses \Colossal\Routing\Utilities\NullMiddleware
 */
class RouterTest extends TestCase
{
    public function testHandleRoutePriorityIsInOrderAdded(): void
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

        $router->handle($this->createServerRequest("GET", "http://localhost:8080/users/2"));
        $this->assertEquals("A", $routeName);
        $router->handle($this->createServerRequest("GET", "http://localhost:8080/users/3"));
        $this->assertEquals("B", $routeName);
    }

    public function testHandleReturns404IfNoMatchingRouteExists(): void
    {
        $router = new Router();

        $response = $router->handle($this->createServerRequest("GET", "http://localhost:8080/users/2"));

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testAddRoute(): void
    {
        $result = new \stdClass();

        $router = new Router();
        $router->addRoute("GET", "%^/users/(?<id>\d+)/?$%", function (int $id) use (&$result): ResponseInterface {
            $result->id     = $id;
            $result->fname  = "Angus";
            $result->lname  = "Wylie";
            return (new Response())->withStatus(200);
        });
        $router->handle($this->createServerRequest("GET", "http://localhost:8080/users/0"));

        $this->assertEquals(0, $result->id);
        $this->assertEquals("Angus", $result->fname);
        $this->assertEquals("Wylie", $result->lname);
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

        $user0Response  = $router->handle($this->createServerRequest("GET", "http://localhost:8080/users/0"));
        $this->assertEquals(DummyController::USERS[0], json_decode($user0Response->getBody()->getContents(), associative: true));

        $user1Response  = $router->handle($this->createServerRequest("GET", "http://localhost:8080/users/1"));
        $this->assertEquals(DummyController::USERS[1], json_decode($user1Response->getBody()->getContents(), associative: true));
    }

    public function testSetMiddleware(): void
    {
        $finalRequest = null;

        $router = new Router();
        $router->addRoute("GET", "%^/users$%", function (ServerRequestInterface $request) use (&$finalRequest): ResponseInterface {
            $finalRequest = $request;
            return (new Response())->withStatus(200);
        });

        $router->setMiddleware(new DummyMiddleware("A", new DummyMiddleware("B", null)));

        $router->handle($this->createServerRequest("GET", "http://localhost:8080/users"));

        if ($finalRequest instanceof ServerRequestInterface) {
            $this->assertTrue($finalRequest->getAttribute("A", false));
            $this->assertTrue($finalRequest->getAttribute("B", false));
        } else {
            $this->fail();
        }
    }

    private function createServerRequest(string $method, string $uri): ServerRequestInterface
    {
        return (new ServerRequest())
            ->withMethod($method)
            ->withUri(Uri::createUriFromString($uri));
    }
}
