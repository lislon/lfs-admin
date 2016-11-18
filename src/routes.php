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
    $result = $this->get('lfsServer')->get($args['id']);
    return $response->withJson($result);
});

$app->patch('/servers/{id}', function ($request, $response, $args) {
    $result = $this->get('lfsServer')->update($args['id'], $request->getParsedBody());
    return $response->withJson($result);

//    $requestJson = $request->getParsedBody();
//    if (isset($requestJson['state'])) {
//        if ($requestJson['state'] == 'running') {
//            $this->logger->addNotice("Server started {$args['id']}");
//            $this->get('lfsServer')->start($args['id']);
//
//        } else if ($requestJson['state'] == 'stopped') {
//            $this->logger->addNotice("Server stopped {$args['id']}");
//            $this->get('lfsServer')->stop($args['id']);
//        }
//    }
//
//
//    return $response->withJson(new \ArrayObject());
});

$app->post('/servers/{id}/restart', function ($request, $response, $args) {
    $this->get('lfsServer')->stop($args['id']);
    $this->get('lfsServer')->start($args['id']);
    $this->logger->addNotice("Server restarted {$args['id']}");
    return $response->withJson(new \ArrayObject());
});

$app->delete('/servers/{id}', function ($request, $response, $args) {
    $this->get('lfsServer')->delete($args['id']);
    $this->logger->addNotice("Server deleted {$args['id']}");
    return $response->withJson(new \ArrayObject());
});

$app->get('/server-images', function ($request, $response, $args) {
    $list = $this->get('lfsImage')->listServers();
    return $response->withJson($list);
});