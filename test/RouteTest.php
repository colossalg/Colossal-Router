<?php

declare(strict_types=1);

namespace Colossal\Routing;

use Colossal\Http\Message\{
    Response,
    ServerRequest,
    Uri
};
use Colossal\MiddlewareQueue\MiddlewareQueue;
use Colossal\Routing\Dummy\DummyMiddleware;
use Psr\Http\Message\{
    ResponseInterface,
    ServerRequestInterface
};
use PHPUnit\Framework\TestCase;

/**
 * @covers \Colossal\Routing\Route
 * @uses \Colossal\Routing\Router
 */
class RouteTest extends TestCase
{
    public function testMatches(): void
    {
        $route = new Route(
            "GET",
            "%^/users/(?<id>\d+)/?$%",
            function (int $id): ResponseInterface {
                return (new Response())->withStatus(200);
            }
        );

        // Test for correct method and pattern
        $this->assertTrue($route->matches($this->createServerRequest("GET", "http://localhost:8080/users/1")));
        $this->assertTrue($route->matches($this->createServerRequest("GET", "http://localhost:8080/users/1/")));

        // Test for incorrect method
        $this->assertFalse($route->matches($this->createServerRequest("POST", "http://localhost:8080/users/1")));

        // Test for incorrect pattern
        $this->assertFalse($route->matches($this->createServerRequest("GET", "http://localhost:8080/users/")));
        $this->assertFalse($route->matches($this->createServerRequest("GET", "http://localhost:8080/users/a")));
    }

    public function testProcessRequestExecutesMiddlewareQueue(): void
    {
        $dummyMiddlewareA = new DummyMiddleware();
        $dummyMiddlewareB = new DummyMiddleware();

        $middlewareQueue = new MiddlewareQueue();
        $middlewareQueue->enqueue($dummyMiddlewareA);
        $middlewareQueue->enqueue($dummyMiddlewareB);

        $route = new Route(
            "GET",
            "%^/users/(?<id>\d+)/?$%",
            function (ServerRequestInterface $request, string $id): ResponseInterface {
                return (new Response())->withStatus(200);
            }
        );

        $request = $this->createServerRequest("GET", "http://localhost:8080/users/1");
        $request = $request->withAttribute(
            Router::COLOSSAL_REQUEST_MIDDLEWARE_QUEUE_ATTR,
            $middlewareQueue
        );

        $route->processRequest($request);

        $this->assertTrue($dummyMiddlewareA->wasExecuted);
        $this->assertTrue($dummyMiddlewareB->wasExecuted);
    }

    public function testHandleWorksForServerRequestArgument(): void
    {
        $results = new \stdClass();

        $route = new Route(
            "GET",
            "%^/users/(?<id>\d+)/?$%",
            function (ServerRequestInterface $request, string $id) use (&$results): ResponseInterface {
                $results->request   = $request;
                $results->id        = $id;
                return (new Response())->withStatus(200);
            }
        );

        $request = $this->createServerRequest("GET", "http://localhost:8080/users/1");
        $route->handle($request);
        $this->assertEquals($request->getMethod(), $results->request->getMethod());
        $this->assertEquals($request->getUri(), $results->request->getUri());
        $this->assertEquals(1, $results->id);
    }

    public function testHandleWorksForDefaultArguments(): void
    {
        $results = new \stdClass();

        $hexChar = "[A-F0-9]";
        $route = new Route(
            "GET",
            "%^/users/(?<userId>$hexChar{4}-$hexChar{4}-$hexChar{4}-$hexChar{8})/posts(?:/(?<postId>\d+))?/?$%",
            function (string $userId, int $postId = 1) use (&$results): ResponseInterface {
                $results->userId = $userId;
                $results->postId = $postId;
                return (new Response())->withStatus(200);
            }
        );

        $userId = "7264-4746-A94E-F101D365";
        $route->handle($this->createServerRequest("GET", "http://localhost:8080/users/$userId/posts"));
        $this->assertEquals($userId, $results->userId);
        $this->assertEquals(1, $results->postId);

        $userId = "C391-4923-9271-9914038A";
        $route->handle($this->createServerRequest("GET", "http://localhost:8080/users/$userId/posts/99"));
        $this->assertEquals($userId, $results->userId);
        $this->assertEquals(99, $results->postId);
    }

    public function testHandleThrowsIfDoesNotMatch(): void
    {
        $route = new Route(
            "GET",
            "%^/users/(?<id>\d+)/?$%",
            function (int $id): ResponseInterface {
                return (new Response())->withStatus(200);
            }
        );

        $this->expectExceptionMessage("Pattern '%^/users/(?<id>\d+)/?$%' did not match path '/index'.");
        $route->handle($this->createServerRequest("GET", "http://localhost:8080/index"));
    }

    public function testHandleThrowsIfParamIsUnionType(): void
    {
        $route = new Route(
            "GET",
            "%^/users/(?<id>\d+)/?$%",
            function (string|int $id): Response {
                return (new Response())->withStatus(200);
            }
        );

        $this->expectExceptionMessage("Parameter 'id' must be a named type, unions and intersections are not allowed.");
        $route->handle($this->createServerRequest("GET", "http://localhost:8080/users/1"));
    }

    public function testHandleThrowsIfParamIsUnsupportedBuiltIn(): void
    {
        $route = new Route(
            "GET",
            "%^/users/(?<hasLicense>true|false)/?$%",
            function (bool $hasLicense): ResponseInterface {
                return (new Response())->withStatus(200);
            }
        );

        $this->expectExceptionMessage("Parameter 'hasLicense' must be ServerRequestInterface, int or string, received 'bool'.");
        $route->handle($this->createServerRequest("GET", "http://localhost:8080/users/true"));
    }

    public function testHandleThrowsIfMandatoryParameterNotProvided(): void
    {
        $route = new Route(
            "GET",
            "%^/users(?<id>/\d+)?/?$%",
            function (int $id): ResponseInterface {
                return (new Response())->withStatus(200);
            }
        );

        $this->expectExceptionMessage("Parameter 'id' is mandatory but was not provided.");
        $route->handle($this->createServerRequest("GET", "http://localhost:8080/users/"));
    }

    public function testHandleThrowsIfReturnTypeIsNotResponseInterface(): void
    {
        $route = new Route(
            "GET",
            "%^/users/(?<id>\d+)/?$%",
            function (int $id): Response {
                return (new Response())->withStatus(200);
            }
        );

        $this->expectExceptionMessage("Return type must be 'ResponseInterface', instead was, 'Colossal\Http\Message\Response'.");
        $route->handle($this->createServerRequest("GET", "http://localhost:8080/users/1"));
    }

    public function testGetters(): void
    {
        $method  = "GET";
        $pattern = "%^/users$%";

        $route = new Route(
            $method,
            $pattern,
            function (int $id): Response {
                return (new Response())->withStatus(200);
            }
        );

        $this->assertEquals($method, $route->getMethod());
        $this->assertEquals($pattern, $route->getPattern());
    }

    private function createServerRequest(string $method, string $uri): ServerRequestInterface
    {
        return (new ServerRequest())
            ->withMethod($method)
            ->withUri(Uri::createUriFromString($uri));
    }
}
