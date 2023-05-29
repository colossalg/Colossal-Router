# Colossal-Router
A simple router implementation utilizing the PSR-15 standardized interfaces.

## Creating the Router

```php

// ---------------------------------------------------------------------------- //
// Creating the router is trivial, the constructor takes no arguments.          //
// Configuration is performed via the method calls on an instance.              //
// ---------------------------------------------------------------------------- //

use Colossal\Routing\Router;
use Psr\Http\Message\{  // These will always be required.
    ResponseInterface,
    ServerRequestInterface
};

$router = new Router();

// ...

$router->handle($request);

```

## Adding Routes

```php

// ---------------------------------------------------------------------------- //
// There are two methods to add routes:                                         //
//      - Registering a closure     - via the Router::addRoute() method.        //
//      - Registering a controller  - via the Router::addController() method.   //
//                                                                              //
// To register a closure the following must be specified:                       //
//      - The HTTP method for the route.                                        //
//      - The PCRE pattern for the route.                                       //
//      - The handler for the route (this is the closure).                      //
//                                                                              //
// To register a controller, for each method intended to be registered          //
// as an end-point, the following must specified via a Route attribute:         //
//      - The HTTP method for the route.                                        //
//      - The PCRE pattern for the route.                                       //
//                                                                              //
// Behind the scenes what will happen is that each method registered as an      //
// end-point will be wrapped in a closure which will call the method on an      //
// instance of the controller. The HTTP method, PCRE pattern, and the closure   //
// together will then be registered via Router::addRoute().                     //
//                                                                              //
// All route handlers, whether they are created via a passed closure or a       //
// closure created from a contoller's method, must return an instance of a      //
// ResponseInterface (see the PSR-7 and PSR-15 standards for more info).        //
// ---------------------------------------------------------------------------- //

use Colossal\Routing\Router;
use Psr\Http\Message\{
    ResponseInterface,
    ServerRequestInterface
};

final class PostController
{
    // This route will match any GET requests to /posts or /posts/
    #[Route(method: "GET", pattern: "%^/posts/?$%")]
    public function getPosts(): ResponseInterface
    {
        // Perform request, create response and return.
        // ...
    }

    // This route will match any POST requests to /posts or /posts/
    #[Route(method: "POST", pattern: "%^/posts/?$%")]
    public function setPosts(): ResponseInterface
    {
        // Perform request, create response and return.
        // ...
    }
}

$router = new Router();

// This route will match any GET requests to /queue or /queue/
$router->addRoute("GET", "%^/queue/?$%", function (): ResponseInterface {
    // Perform request, create response and return.
    // ...
});

// This route will match any POST requests to /queue or /queue/
$router->addRoute("POST", "%^/queue/?$%", function (): ResponseInterface {
    // Perform request, create response and return.
    // ...
});

// Register the controller. All of the reflection magic happens behind the scenes.
$router->addController(UserController::class);

// ...

$router->handle($request);

```

## Route Parameters

```php
// ---------------------------------------------------------------------------- //
// To provide routes with parameters:                                           //
//      - Create a named capture group in the route's PCRE pattern.             // 
//      - Add a parameter to the closure/controller method with:                //
//          - An identical name to the capture group.                           //
//          - One of the following types:                                       //
//              - int                                                           //
//              - string                                                        //
//                                                                              //
// There is one exception to above. The route may take an argument with type    //
// ServerRequestInterface in addition to any other route parameters. In this    //
// instance no capture group must be specified in the route's PCRE pattern.     //
// ---------------------------------------------------------------------------- //

use Colossal\Routing\Router;
use Psr\Http\Message\{
    ResponseInterface,
    ServerRequestInterface
};

$router = new Router();

// This route will match any GET requests to /users/<id> or /users/<id>/
// <id> will be:
//      - Extracted from the path.
//      - Cast to an int.
//      - Passed as the $id param of the route handler.
$router->addRoute("GET", "%^/users/(?<id>\d+)/?$%", function (int $id): ResponseInterface {
    echo "User id = $id";
    // Perform request, create response and return.
    // ...
});

// This route will match any POST requests to /users/<id>/profile or /users/<id>/profile/
// $request will be the ServerRequestInterface that the router was dispatched to handle.
// <id> will be:
//      - Extracted from the path.
//      - Cast to an int.
//      - Passed as the $id param of the route handler.
$router->addRoute(
    "POST",
    "%^/users/(?<id>\d+)/profile/?$%",
    function (ServerRequestInterface $request, int $id): ResponseInterface {
        echo "User id = $id";
        // Perform request, create response and return.
        // Ex. Parse JSON from the body of $request.
        // ...
    }
);

// ...

$router->handle($request);

```

## Route Resolution

```php
// ---------------------------------------------------------------------------- //
// The route resolution is simple; it's essentially first-come-first-served.    //
//                                                                              //
// The route's are examined in order that they were registered with the router  //
// checking whether both:                                                       //
//      - The route's HTTP method matches the request method.                   //
//      - The route's PCRE pattern matches the request URI path.                //
//                                                                              //
// Once a route satisfying the above is found, the route's handler is called    //
// for the request and the resulting response returned.                         //
// ---------------------------------------------------------------------------- //

use Colossal\Routing\Router;
use Psr\Http\Message\{
    ResponseInterface,
    ServerRequestInterface
};

$router = new Router();

$router->addRoute("GET", "%^/page/(A|B)$%, function (): ResponseInterface {
    echo "Route 1";
    // Perform request, create response and return.
    // ...
});
$router->addRoute("GET", "%^/page/(B|C)$%, function (): ResponseInterface {
    echo "Route 2";
    // Perform request, create response and return.
    // ...
});
$router->addRoute("GET", "%^/page/(C|D)$%, function (): ResponseInterface {
    echo "Route 3";
    // Perform request, create response and return.
    // ...
});

// A GET request with path /page/B will echo "Route 1".
// A GET request with path /page/C will echo "Route 2".

// ...

$router->handle($request);

```

## Router Middleware

```php
// ---------------------------------------------------------------------------- //
// Middleware implementing the PSR-15 MiddlewareInterface may be registered     //
// with the router. If so, once a request is matched to a route, rather than    //
// directly invoking the route's handler, the middleware's process() method     //
// will be invoked with the route passed as the $handler parameter.             //
// ---------------------------------------------------------------------------- //

use Colossal\Routing\Router;
use Psr\Http\Message\{
    ResponseInterface,
    ServerRequestInterface
};
use Psr\Http\Server\{
    MiddlewareInterface,
    RequestHandlerInterface
};

final class AuthMiddleware implements MiddlewareInterface
{
    // ...
    
    public function process(
        ServerRequestInterface $request,
        RequesthandlerInterface $handler
    ): ResponseInterace {
        // Perform request, create response and return.
        // ...
    }
    
    // ...
}

$router = new Router();

$router->setMiddleware(new AuthMiddleware());

// ...

$router->handle($request);

```

## Development Tips

### Running PHPUnit Test Suites

Run the PHPUnit test suites with the following command:

```bash
>> .\vendor\bin\phpunit
```

To additionally print the test coverage results to stdout run the following command:

```bash
>> .\vendor\bin\phpunit --coverage-html="coverage"
```

### Running PHPStan Code Quality Analysis

Run the PHPStan code quality analysis with the following command:

```bash
>> .\vendor\bin\phpstan --configuration=phpstan.neon --xdebug
```

### Running PHP Code Sniffer Code Style Analysis

Run the PHP Code Sniffer code style analysis with the following commands:

```bash
>> .\vendor\bin\phpcs --standard=phpcs.xml src
>> .\vendor\bin\phpcs --standard=phpcs.xml test
```

To fix automatically resolve issues found by PHP Code Sniffer run the following commands:

```bash
>> .\vendor\bin\phpcbf --standard=phpcs.xml src
>> .\vendor\bin\phpcbf --standard=phpcs.xml test
```
