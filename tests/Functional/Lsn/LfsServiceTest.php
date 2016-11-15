<?php

namespace Tests\Functional\Lfs;
use Docker\Docker;
use Docker\DockerClient;
use Slim\App;

/**
 * Created by PhpStorm.
 * User: ele
 * Date: 11/15/16
 * Time: 12:41 PM
 */
class LfsServiceTest extends \PHPUnit_Framework_TestCase {

    public function testCreateServer() {

        $httpClient = DockerClient::createFromEnv();
        

        $s = new \Lsn\LfsServerService(new Docker(), ['buildPath' => '/home/ele/src-dropbox/docker/lfs/build-data']);
        $s->create("6050", "0.6M", 6050, []);
        
    }
}