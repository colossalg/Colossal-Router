<?php

declare(strict_types=1);

namespace Colossal\Routing;

use Colossal\Http\Message\{
    Response,
    ServerRequest,
    Uri
};
use Psr\Http\Message\{
    ResponseInterface,
    ServerRequestInterface
};
use PHPUnit\Framework\TestCase;

/**
 * @covers \Colossal\Routing\Route
 */
class RouteTest extends TestCase
{
    public function testGetMethod(): void
    {
        $pattern = "%/users/(?<id>\d+)/?%";
        $handler = function(int $id): ResponseInterface {
            return (new Response())->withStatus(200);
        };
        $route = new Route("GET", $pattern, $handler);
        $this->assertEquals("GET", $route->getMethod());
        $route = new Route("POST", $pattern, $handler);
        $this->assertEquals("POST", $route->getMethod());
    }

    public function testGetPattern(): void
    {
        $pattern = "%/users/(?<id>\d+)/?%";
        $route = new Route(
            "GET",
            $pattern,
            function(int $id): ResponseInterface {
                return (new Response())->withStatus(200);
            }
        );
        $this->assertEquals($pattern, $route->getPattern());
    }

    public function testGetHandler(): void
    {
        $handler = function(int $id): ResponseInterface {
            return (new Response())->withStatus(200);
        };
        $route = new Route("GET",  "%/users/(?<id>\d+)/?%", $handler);
        $this->assertEquals($handler, $route->getHandler());
    }

    public function testMatches(): void
    {
        $route = new Route(
            "GET",
            "%^/users/(?<id>\d+)/?$%",
            function(int $id): ResponseInterface {
                return (new Response())->withStatus(200);
            }
        );

        // Test for correct method and pattern
        $this->assertTrue($route->matches($this->createServerRequest("GET", "http://localhost:8080/users/1")));
        $this->assertTrue($route->matches($this->createServerRequest("GET", "http://localhost:8080/users/1/")));
        $this->assertTrue($route->matches($this->createServerRequest("GET", "http://localhost:8080/users/234/")));

        // Test for incorrect method
        $this->assertFalse($route->matches($this->createServerRequest("POST", "http://localhost:8080/users/1")));

        // Test for incorrect pattern
        $this->assertFalse($route->matches($this->createServerRequest("GET", "http://localhost:8080/users/")));
        $this->assertFalse($route->matches($this->createServerRequest("GET", "http://localhost:8080/users/a")));
        $this->assertFalse($route->matches($this->createServerRequest("GET", "http://localhost:8080/users/1//")));
    }

    public function testHandleWorksForServerRequestArgument(): void
    {
        $results = new \stdClass();
        $results->request   = null;
        $results->id        = null;

        $route = new Route(
            "GET",
            "%^/users/(?<id>\d+)/?$%",
            function(ServerRequestInterface $request, string $id) use(&$results): ResponseInterface {
                $results->request   = $request;
                $results->id        = $id;
                return (new Response())->withStatus(200);
            }
        );

        // Assert that the method works for server request arguments

        $request = $this->createServerRequest("GET", "http://localhost:8080/users/1");
        $route->handle($request);
        $this->assertEquals($request->getMethod(), $results->request->getMethod());
        $this->assertEquals($request->getUri(), $results->request->getUri());
        $this->assertEquals(1, $results->id);
    }

    public function testHandleWorksForDefaultArguments(): void
    {
        $results = new \stdClass();
        $results->userId = null;
        $results->postId = null;

        $hexChar = "[A-F0-9]";
        $route = new Route(
            "GET",
            "%^/users/(?<userId>$hexChar{4}-$hexChar{4}-$hexChar{4}-$hexChar{8})/posts(?:/(?<postId>\d+))?/?$%",
            function(string $userId, int $postId = 1) use(&$results): ResponseInterface {
                $results->userId = $userId;
                $results->postId = $postId;
                return (new Response())->withStatus(200);
            }
        );

        // Assert that the method works for default arguments

        $guid = "7264-4746-A94E-F101D365";
        $route->handle($this->createServerRequest("GET", "http://localhost:8080/users/$guid/posts"));
        $this->assertEquals($guid, $results->userId);
        $this->assertEquals(1, $results->postId);

        $guid = "C391-4923-9271-9914038A";
        $route->handle($this->createServerRequest("GET", "http://localhost:8080/users/$guid/posts/99"));
        $this->assertEquals($guid, $results->userId);
        $this->assertEquals(99, $results->postId);
    }

    public function testHandleThrowsIfDoesNotMatch(): void
    {
        // Assert that the method throws if the pattern does not match
        $this->expectExceptionMessage("Pattern '%^/users/(?<id>\d+)/?$%' did not match path '/index'.");
        $route = new Route(
            "GET",
            "%^/users/(?<id>\d+)/?$%",
            function(int $id): ResponseInterface {
                return (new Response())->withStatus(200);
            }
        );
        $route->handle($this->createServerRequest("GET", "http://localhost:8080/index"));
    }

    public function testHandleThrowsIfParamIsUnionType(): void
    {
        // Assert that the method throws if any of the parameters are union types
        $this->expectExceptionMessage("Parameter 'id' must be a named type, unions and intersections are not allowed.");
        $route = new Route(
            "GET",
            "%^/users/(?<id>\d+)/?$%",
            function(string|int $id): Response {
                return (new Response())->withStatus(200);
            }
        );
        $route->handle($this->createServerRequest("GET", "http://localhost:8080/users/1"));
    }

    public function testHandleThrowsIfParamIsUnsupportedBuiltIn(): void
    {
        // Assert that the method throws if any of the parameters are unsupported built in types
        $this->expectExceptionMessage("Parameter 'hasLicense' must be ServerRequestInterface, int or string, received 'bool'.");
        $route = new Route(
            "GET",
            "%^/users/(?<hasLicense>true|false)/?$%",
            function(bool $hasLicense): ResponseInterface {
                return (new Response())->withStatus(200);
            }
        );
        $route->handle($this->createServerRequest("GET", "http://localhost:8080/users/true"));
    }

    public function testHandleThrowsIfMandatoryParameterNotProvided(): void
    {
        // Assert that the method throws if any of the mandatory parameters are not provided
        $this->expectExceptionMessage("Parameter 'id' is mandatory but was not provided.");
        $route = new Route(
            "GET",
            "%^/users(?<id>/\d+)?/?$%",
            function(int $id): ResponseInterface {
                return (new Response())->withStatus(200);
            }
        );
        $route->handle($this->createServerRequest("GET", "http://localhost:8080/users/"));
    }

    public function testHandleThrowsIfReturnTypeIsNotResponseInterface(): void
    {
        // Assert that the method throws if the return type is not the response interface
        $this->expectExceptionMessage("Return type must be 'ResponseInterface', instead was, 'Colossal\Http\Message\Response'.");
        $route = new Route(
            "GET",
            "%^/users/(?<id>\d+)/?$%",
            function(int $id): Response {
                return (new Response())->withStatus(200);
            }
        );
        $route->handle($this->createServerRequest("GET", "http://localhost:8080/users/1"));
    }

    private function createServerRequest(string $method, string $uri): ServerRequestInterface
    {
        return (new ServerRequest())
            ->withMethod($method)
            ->withUri(Uri::createUriFromString($uri));
    }
}