<?php

namespace Tests\Functional\Lfs;

use Docker\Docker;
use Docker\DockerClient;
use Lsn\Helper\DockerLogClientDecorator;
use Lsn\LfsServerService;
use Lsn\XServerService;
use Monolog\Logger;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Slim\App;


/**
 * Created by PhpStorm.
 * User: ele
 * Date: 11/15/16
 * Time: 12:41 PM
 */
class LfsServiceTest extends \PHPUnit_Framework_TestCase {

    /**
     * @var LfsServerService
     */
    private $service;

    protected function setUp()
    {
        $mongoLog = new Logger('name');
        $client = new DockerLogClientDecorator(DockerClient::createFromEnv(), $mongoLog);
        $docker = new Docker($client);
        $this->service = new LfsServerService($docker, [
            'buildPath' => '/home/ele/src-dropbox/docker/lfs/build-data'
        ], new XServerService($docker));
    }


    public function testList() {
        $containerInfos = $this->service->listServers();
    }

    public function testCreateServer() {
        $id = $this->service->create([
            'port' => 6050,
            'version' => '0.6M',
            'pereulok' => true,
            'host' => 'very_test',
            'admin' => 123,
            'pass' => 123,
            'welcome' => 'Hello!'
        ]);

        $this->service->start($id);

    }

    public function testStopServer()
    {
        $this->service->stop("mad_stallman");
        $this->service->delete("mad_stallman");
    }


}