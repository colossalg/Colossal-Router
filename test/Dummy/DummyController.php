<?php

declare(strict_types=1);

namespace Colossal\Routing\Dummy;

use Colossal\Http\Message\{
    Response,
    Stream
};
use Colossal\Routing\Route;
use Psr\Http\Message\{
    ResponseInterface,
    ServerRequestInterface
};

class DummyController
{
    public const USERS = [
        0 => [
            "fname" => "Angus",
            "lname" => "Wylie"
        ],
        1 => [
            "fname" => "John",
            "lname" => "Doe"
        ]
    ];

    #[Route(method: "GET", pattern: "%^/users/(?<id>\d+)/?$%")]
    public function getUser(int $id): ResponseInterface
    {
        $resource = fopen("php://temp", "r+");
        if ($resource === false) {
            throw new \RuntimeException("Call to fopen() failed.");
        }
        $body = new Stream($resource);
        $body->write(json_encode(self::USERS[$id]));

        return (new Response())
            ->withStatus(200)
            ->withBody($body);
    }
}
