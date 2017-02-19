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
    $xServer = new \Lsn\Service\Aux\XServerContainerService($c->get('docker'), $c->get('xserverImageBuilder'));
    $service = new \Lsn\Service\Lfs\LfsContainerService($c->get('docker'), $settings, $xServer, $c->get('lfsImageManager'), $c->get('settings')['env']);
    return $service;
};

$container['xserverImageBuilder'] = function ($c) {
    $settings = $c->get('settings')['docker'];

    $service = new \Lsn\Helper\DockerImageManager('x11server', $settings['dockerfiles_path'].'/x11server', $c->get('imageManager'));
    return $service;
};

$container['lfsImageManager'] = function ($c) {
    $settings = $c->get('settings')['docker'];
    $service = new \Lsn\Helper\DockerImageManager('lfs-server', $settings['dockerfiles_path'].'/lfs-server', $c->get('imageManager'));

    $service->setImageValidator(new \Lsn\Validator\LfsImageValidator());
    return $service;
};

$container['imageManager'] = function ($c) {
    return $c->get('docker')->getImageManager();
};

