<?php

use \Psr\Http\Message\ServerRequestInterface;
use Slim\Http\Response;

$app->get('/', function (ServerRequestInterface $request, \Slim\Http\Response $response, $args) {
    return $response->withRedirect('/servers', 301);
});

$app->get('/servers', function ($request, $response, $args) {
    $list = $this->get('lfsServer')->listServers();
    return $response->withJson($list);
});


$app->post('/servers', function (ServerRequestInterface $request, Response $response, $args) {
    $id = $this->get('lfsServer')->create($request->getParsedBody());
    $this->logger->addNotice("New server created id=$id");
    return $response->withJson(['id' => $id], 201);
});

$app->get('/servers/{id}', function ($request, $response, $args) {
    $result = $this->get('lfsServer')->get($args['id']);
    return $response->withJson($result);
});

$app->get('/servers/{id}/logs', function ($request, $response, $args) {
    $logs = $this->get('lfsServer')->getLogs($args['id']);
    return $response->withJson($logs);
});

$app->get('/servers/{id}/stats', function ($request, $response, $args) {
    $logs = $this->get('lfsServer')->getStats($args['id']);
    return $response->withJson($logs);
});

$app->patch('/servers/{id}', function ($request, $response, $args) {
    $result = $this->get('lfsServer')->patch($args['id'], $request->getParsedBody());
    return $response->withJson($result);
});

$app->post('/servers/{id}/start', function ($request, $response, $args) {
    $this->get('lfsServer')->start($args['id']);
    $this->logger->addNotice("Server started {$args['id']}");
    return $response->withJson(new \ArrayObject());
});

$app->post('/servers/{id}/stop', function ($request, $response, $args) {
    $this->get('lfsServer')->stop($args['id']);
    $this->logger->addNotice("Server stopped {$args['id']}");
    return $response->withJson(new \ArrayObject());
});

$app->post('/servers/{id}/restart', function ($request, $response, $args) {
    $this->get('lfsServer')->stop($args['id']);
    $this->get('lfsServer')->start($args['id']);
    $this->logger->addNotice("Server restarted {$args['id']}");
    return $response->withJson(new \ArrayObject());
});

$app->delete('/servers/{id}', function ($request, Response $response, $args) {
    $this->get('lfsServer')->delete($args['id']);
    $this->logger->addNotice("Server deleted {$args['id']}");
    return $response->withStatus(204);
});

$app->get('/server-images', function ($request, $response, $args) {
    $list = $this->get('lfsImage')->getImages();
    return $response->withJson($list);
});

$app->post('/server-images/{name}', function (ServerRequestInterface $request, $response, $args) {
    $result = $this->get('lfsImage')->createImage($args['name'], $request->getBody());
    return $response->withJson($result);
});

$app->delete('/server-images/{name}', function (ServerRequestInterface $request, $response, $args) {
    $this->get('lfsImage')->deleteImage($args['name']);
    return $response->withStatus(204);
});
