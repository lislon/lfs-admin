<?php

use \Psr\Http\Message\ServerRequestInterface;

$app->get('/servers', function ($request, $response, $args) {
    $list = $this->get('lfsServer')->listServers();
    return $response->withJson($list);
});


$app->post('/servers', function (ServerRequestInterface $request, \Slim\Http\Response $response, $args) {
    $id = $this->get('lfsServer')->create($request->getParsedBody());
    $this->logger->addNotice("New server created id=$id");
    return $response->withJson(['id' => $id]);
});

$app->get('/servers/{id}', function ($request, $response, $args) {
    $list = $this->get('lfsServer')->listServers();
    return $response->withJson($list);
});

$app->patch('/servers/{id}', function ($request, $response, $args) {
    $this->get('lfsServer')->start($args['id']);
    $this->logger->addNotice("Server started {$args['id']}");
    return $response->withJson(new \ArrayObject());
});

$app->post('/servers/{id}/stop', function ($request, $response, $args) {
    $this->get('lfsServer')->stop($args['id']);
    $this->logger->addNotice("Server stopped {$args['id']}");
    return $response->withJson(new \ArrayObject());
});

$app->delete('/servers/{id}', function ($request, $response, $args) {
    $this->get('lfsServer')->delete($args['id']);
    $this->logger->addNotice("Server deleted {$args['id']}");
    return $response->withJson(new \ArrayObject());
});