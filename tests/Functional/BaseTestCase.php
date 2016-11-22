<?php

namespace Tests\Functional;

use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\Environment;

/**
 * This is an example class that shows how you could set up a method that
 * runs the application. Note that it doesn't cover all use-cases and is
 * tuned to the specifics of this skeleton app, so if your needs are
 * different, you'll need to change it.
 */
class BaseTestCase extends \PHPUnit_Framework_TestCase
{
    /**
     * @return App
     */
    protected static function getApp()
    {
        // Use the application settings
        $settings = require __DIR__ . '/../../src/settings.php';

        // mark containers with test label
        $settings['settings']['env'] = 'test';

        // Instantiate the application
        $app = new App($settings);

        // Set up dependencies
        require __DIR__ . '/../../src/dependencies.php';

        // Register middleware

        require __DIR__ . '/../../src/middleware.php';

        // Register routes
        require __DIR__ . '/../../src/routes.php';

        return $app;
    }

    /**
     * Process the application given a request method and URI
     *
     * @param string $requestMethod the request method (e.g. GET, POST, etc.)
     * @param string $requestUri the request URI
     * @param array|object|null $requestData the request data
     * @return \Slim\Http\Response
     */
    protected function runApp($requestMethod, $requestUri, $requestData = null, $contentType = null)
    {
        $app = self::getApp();

        // Create a mock environment for testing with
        $environment = Environment::mock(
            [
                'REQUEST_METHOD' => $requestMethod,
                'REQUEST_URI' => $requestUri
            ]
        );

        // Set up a request object based on the environment
        $request = Request::createFromEnvironment($environment);

        // Add request data, if it exists
        if (isset($requestData)) {
            $request = $request->withParsedBody($requestData);
        }
        if (isset($contentType)) {
            $request = $request->withHeader('Content-Type', $contentType);
        }

        // Set up a response object
        $response = new Response();

        // Process the application
        $response = $app->process($request, $response);

        // Return the response
        return $response;
    }

    protected function assertResponse($expected, Response $response)
    {
        if ($expected != $response->getStatusCode()) {
            $error = json_decode($response->getBody(), true);

            $this->assertEquals($expected, $response->getStatusCode(), isset($error['message']) ? $error['message'] : null);
        }
    }
}
