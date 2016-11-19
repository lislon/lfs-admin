<?php
// Application middleware

// e.g: $app->add(new \Slim\Csrf\Guard);

use Psr\Http\Message\ServerRequestInterface;

$c = $app->getContainer();
$c['errorHandler'] = function ($c) {
    return function ($request, $response, $exception) use ($c) {
        $code = 500;
        $message = 'Internal server error';
        if ($exception instanceof \Lsn\Exception\LsnException) {
            $code = 400;
            $message = $exception->getMessage();
            $c['logger']->addError($exception->getMessage()/*, ['trace' => $exception->getTraceAsString()]*/);
        } else {
            $c['logger']->addError($exception->getMessage());
            if ($c->get('settings')['env'] != 'production') {
                $message = $exception->getMessage();
            }
        }


        return $c['response']->withStatus($code)
            ->withHeader('Content-Type', 'application/json')
            ->withJson(['status' => $code, 'message' => $message ]);
    };
};

$c['phpErrorHandler'] = function ($c) {
    return function ($request, $response, $error) use ($c) {
        $message = 'Internal server error';
        if ($c->get('settings')['env'] != 'production') {
            $message = "{$error}";
        }
        return $c['response']->withStatus(500)
            ->withHeader('Content-Type', 'application/json')
            ->withJson(['status' => 500, 'message' => $message ]);
    };
};

$app->add(function (ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response, $next) {
    $this->logger->info("REQUEST {$request->getMethod()} {$request->getUri()}", $request->getParsedBody() ?: []);
    $response = $next($request, $response);
    $this->logger->info("RESPONSE {$response->getStatusCode()} {$response->getBody()}");

    return $response;
});
