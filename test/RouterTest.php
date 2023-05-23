<?php

declare(strict_types=1);

namespace Colossal\Routing;

use Colossal\Http\Message\{
    ServerRequest,
    Uri
};
use Colossal\Routing\Dummy\DummyController;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @covers \Colossal\Routing\Router
 * @uses \Colossal\Routing\Route
 * @uses \Colossal\Routing\Utilities\NullMiddleware
 */
class RouterTest extends TestCase
{
    public function testHandle(): void
    {
        // TODO
    }

    public function testAddRoute(): void
    {
        // TODO
    }

    public function testAddRouteThrowsIfRouteAddedTwice(): void
    {
        // TODO
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
        // TODO
    }

    private function createServerRequest(string $method, string $uri): ServerRequestInterface
    {
        return (new ServerRequest())
            ->withMethod($method)
            ->withUri(Uri::createUriFromString($uri));
    }
}
