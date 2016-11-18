<?php
// DIC configuration

use Docker\DockerClient;

$container = $app->getContainer();

// view renderer
$container['renderer'] = function ($c) {
    $settings = $c->get('settings')['renderer'];
    return new Slim\Views\PhpRenderer($settings['template_path']);
};

// monolog
$container['logger'] = function ($c) {
    $settings = $c->get('settings')['logger'];
    $logger = new Monolog\Logger($settings['name']);
    $logger->pushProcessor(new Monolog\Processor\UidProcessor());
    $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], $settings['level']));
    return $logger;
};

$container['docker'] = function ($c) {

    $httpClient = new \Lsn\Helper\DockerLogClientDecorator(DockerClient::createFromEnv(), $c->get('logger'));
    $docker = new \Docker\Docker($httpClient);
    return $docker;
};

$container['lfsServer'] = function ($c) {
    $settings = $c->get('settings')['docker'];
    $xServer = new \Lsn\XServerService($c->get('docker'));
    $service = new \Lsn\LfsServerService($c->get('docker'), $settings, $xServer);
    return $service;
};

$container['lfsVersion'] = function ($c) {
    $settings = $c->get('settings')['docker'];
    $service = new \Lsn\LfsVersionService($settings);
    return $service;
};
