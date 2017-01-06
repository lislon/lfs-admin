<?php

namespace Tests\Functional;

use Lsn\Helper\TempDir;
use Naucon\File\File;
use Slim\Http\RequestBody;

class InsimServiceTest extends BaseTestCase
{
    private function createLislonInsim()
    {
        $response = $this->runApp('POST', '/insim', self::serverTemplate);
        $this->assertResponse(201, $response);
        $json = json_decode($response->getBody(), true);
        return $this->serverId = $json['id'];
    }
}