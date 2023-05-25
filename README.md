# Colossal-Router
A simple router implementation utilizing the PSR-15 standardized interfaces.

## Function Routes

```php

// Creating the router is very simple.
$router = new Router();

// -------------------------------------------------------------------------- //
// We can directly register routes to the router. To do so we must specify    //
//     - The HTTP method.                                                     //
//     - The PCRE pattern.                                                    //
//     - The handler for the route (must return ResponseInterface).           //
// -------------------------------------------------------------------------- //

// This route will match any GET requests to /index or /index/
$router->addRoute("GET",  "%^/index/?$%", function (): ResponseInterface {
    // Perform request, create response and return.
    // ...
});

// This route will match any POST requests to /queue or /queue/
$router->addRoute("POST", "%^/queue/?$%", function (): ResponseInterface {
    // Perform request, create response and return.
    // ...
});

// -------------------------------------------------------------------------- //
// Named capture groups may be used to provide arguments to the route handler.//
// These capture groups must:                                                 //
//     - Match the name of an argument in the route handler.                  //
//     - Be convertible to the type of the corresponding argument.            //
// -------------------------------------------------------------------------- //

// This route will match any GET requests to /users/<id> or /users/<id>/
// <id> will be converted to an int and passed to the handler.
$router->addRoute("GET",  "%^/users/(?<id>[0-9]+)/?$%", function (int $id): ResponseInterface {
    echo "User id = $id.";

    // Perform request, create response and return.
    // ...
});

// This route will match any POST requests to /users/<id> or /users/<id>/
// <id> will be converted to an int and passed to the handler.
$router->addRoute("POST", "%^/users/(?<id>[0-9]+)/?$%", function (int $id): ResponseInterface {
    echo "User id = $id.";

    // Perform request, create response and return.
    // ...
});

// -------------------------------------------------------------------------- //
// Besides the named captures, route handlers may also take an argument for   //
// the ServerRequestInterface if they require any of the information required //
// from the raw request.                                                      //
// -------------------------------------------------------------------------- //

// This route will match any GET requests to /posts/<id> or /posts/<id>/
// <id> will be converted to an int and passed to the handler.
$router->addRoute(
    "GET", 
    "%^/posts/(?<id>[0-9]+)/?$%",
    function (ServerRequestInterface $request, int $id): ResponseInterface {
        echo "User id = $id.";
        var_dump($request->getHeaders());

        // Perform request, create response and return.
        // ...
    }
);
```

## Controller Routes

```php

// -------------------------------------------------------------------------- //
// Behind the scenes what happens is that the router will examine all of the  //
// methods which have the attribute:                                          //
// #[Route(method: <http-method>, pattern: <pcre-pattern>)]                   //
// This will then be converted in to a normal route where:                    //
// - The route method is <http-method>.                                       //
// - The route pattern is <pcre-pattern>.                                     //
// - The route handler is a closure that:                                     //
//      * Wrapping an instance of the controller class.                       //
//      * Calls the method with any route parameters from capture groups.     //
//      * Returns an instance of a ResponseInterface.                         //
// -------------------------------------------------------------------------- //

final class UserController
{
    // This route will match any GET requests to /users/<id> or /users/<id>/
    // <id> will be converted to an int and passed to the method.
    #[Route(method: "GET", pattern: "%^/users/(?<id>[0-9]+)/?$%")]
    public function getUser(int $id): ResponseInterface
    {
        echo "User id = $id.";

        // Perform request, create response and return.
        // ...
    }

    // This route will match any POST requests to /users/<id> or /users/<id>/
    // <id> will be converted to an int and passed to the method.
    #[Route(method: "POST", pattern: "%^/users/(?<id>[0-9]+)/?$%")]
    public function postUser(int $id): ResponseInterface
    {
        echo "User id = $id.";

        // Perform request, create response and return.
        // ...
    }
}

$router = new Router();

// Register the controller. All of the reflection magic happens behind the scenes.
$router->addController(UserController::class);

// Request is the ServerRequestInterface for the current request being handled.
// The method and uri path are automatically sourced from the request.
$router->handle($request);

```

## Middleware

```php
// -------------------------------------------------------------------------- //
// Middleware may be registered with the router. If so, the middleware will   //
// be used to process requests prior to delegating to the route handler for   //
// the final handling of the request.                                         //
//                                                                            //
// E.g. the router will call the middleware's process method with the route   //
// as the handler argument.                                                   //
// -------------------------------------------------------------------------- //

final class AuthMiddleware implements MiddlewareInterface
{
    // ...
}

$router = new Router();

// Register routes with the router.
// ...

$router->setMiddleware(new AuthMiddleware());

// Request is the ServerRequestInterface for the current request being handled.
// The method and uri path are automatically sourced from the request.
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
