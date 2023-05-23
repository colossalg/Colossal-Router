<?php

declare(strict_types=1);

namespace Colossal\Routing;

use Psr\Http\Message\{
    ResponseInterface,
    ServerRequestInterface
};
use Psr\Http\Server\RequestHandlerInterface;

class Route implements RequestHandlerInterface
{
    /**
     * Constructor.
     * @param string $method    This route's method.
     * @param string $pattern   This route's pattern.
     * @param \Closure $handler This route's handler.
     */
    public function __construct(string $method, string $pattern, \Closure $handler)
    {
        $this->method   = $method;
        $this->pattern  = $pattern;
        $this->handler  = $handler;
    }

    public function matches(ServerRequestInterface $request): bool
    {
        return (
            $this->method === $request->getMethod() &&
            boolval(preg_match($this->pattern, $request->getUri()->getPath()))
        );
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Parse the request params from the URI's path component.

        $requestPath    = $request->getUri()->getPath();
        $requestParams  = [];
        if (
            !preg_match(
                $this->pattern,
                $requestPath,
                $requestParams,
                PREG_UNMATCHED_AS_NULL
            )
        ) {
            throw new \RuntimeException("Pattern '$this->pattern' did not match path '$requestPath'.");
        }

        // Assert that the param types of the handler are as expected.
        // Also prepare the params to have the correct order and type.

        $handlerParams = [];
        $reflectionFunction = new \ReflectionFunction($this->handler);
        foreach($reflectionFunction->getParameters() as $param) {
            $paramType      = $param->getType();
            $paramName      = $param->getName();
            $paramPosition  = $param->getPosition();

            if (!($paramType instanceof \ReflectionNamedType)) {
                throw new \RuntimeException(
                    "Parameter '$paramName' must be a named type, unions and intersections are not allowed."
                );
            }

            // If the param is the server request assume its value.
            if ($paramType->getName() === ServerRequestInterface::class) {
                $handlerParams[$paramPosition] = $request;
                continue;
            }

            if (array_key_exists($paramName, $requestParams) && !is_null($requestParams[$paramName])) {
                $handlerParams[$paramPosition] = match ($paramType->getName()) {
                    "int"       => intval($requestParams[$paramName]),
                    "string"    => strval($requestParams[$paramName]),
                    default     => throw new \RuntimeException(
                        "Parameter '$paramName' must be ServerRequestInterface, int or string, received '$paramType'."
                    )
                };
            } else if ($param->isDefaultValueAvailable()) {
                $handlerParams[$paramPosition] = $param->getDefaultValue();
            } else {
                throw new \RuntimeException("Parameter '$paramName' is mandatory but was not provided.");
            }
        }

        // Assert that the return type of the handler is as expected.

        $returnType = $reflectionFunction->getReturnType();
        if (
            !($returnType instanceof \ReflectionNamedType) ||
            $returnType->getName() !== ResponseInterface::class
        ) {
            throw new \RuntimeException("Return type must be 'ResponseInterface', instead was, '$returnType'.");
        }

        return $reflectionFunction->invokeArgs($handlerParams);
    }

    /**
     * Get this route's method.
     * @return string $method
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Get this route's pattern.
     * @return string $pattern
     */
    public function getPattern(): string
    {
        return $this->pattern;
    }

    /**
     * Get this route's handler.
     * @return \Closure $handler
     */
    public function getHandler(): \Closure
    {
        return $this->handler;
    }

    /**
     * @var string $method This route's method.
     */
    private string $method;

    /**
     * @var string $pattern This route's pattern.
     */
    private string $pattern;

    /**
     * @var \Closure $handler This route's handler.
     */
    private \Closure $handler;
}
