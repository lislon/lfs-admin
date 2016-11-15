<?php

// Show list of servers
$app->get('/servers', function ($request, $response, $args) {

    $list = array(
        array('id' => 1, 'name' => 'Server 1'),
        array('id' => 2, 'name' => 'Server 2'),
    );

    return $response->withJson($list);
});

// restart particular server
$app->get('/servers/{name}/restart', function ($request, $response, $args) {
    // Sample log message
    $this->logger->info("Slim-Skeleton '/' route");

    return $response->withJson(array("message" => "Restarting {$args['name']}..."));
    // Render index view
    // return $this->renderer->render($response, 'index.phtml', $args);
});
