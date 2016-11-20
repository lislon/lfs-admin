<?php

namespace Tests\Functional;

class LFsHttpServiceTest extends BaseTestCase
{
    private $serverId = null;

    const serverTemplate = [
        'port' => 58999,
        'image' => '0.6M',
        'host' => 'lislon test',
        'usemaster' => 'no',
        'pereulok' => true,
        'admin' => 'admin',
        'pass' => 'test',
    ];


    public static function setUpBeforeClass()
    {
        self::getApp()->getContainer()->get('lfsServer')->stopAllTestContainers();
    }


    private function createServer()
    {
        if ($this->serverId != null) {
            throw new \Exception("Server is already created. Block second creation in order to prevent polluting space");
        }
        $response = $this->runApp('POST', '/servers', self::serverTemplate);
        $this->assertResponse(201, $response);
        $json = json_decode($response->getBody(), true);
        return $this->serverId = $json['id'];
    }

    /**
     * Clean up created server if any.
     */
    protected function tearDown()
    {
        if ($this->serverId != null) {
            $this->runApp('DELETE', "/servers/{$this->serverId}");
        }
        parent::tearDown();
    }

    public function testCreate()
    {
        $response = $this->runApp('POST', '/servers', self::serverTemplate);

        $this->assertResponse(201, $response);
        $json = json_decode($response->getBody(), true);

        $this->assertArrayHasKey('id', $json);

        $this->serverId = $json['id'];

        $response = $this->runApp('GET', "/servers/{$json['id']}");

        $this->assertResponse(200, $response);
        $json = json_decode($response->getBody(), true);

        $this->assertEquals('lislon test', $json['host']);
    }

    public function testPatch()
    {
        $config = [
            'pass' => 'newpass'
        ];

        $id = $this->createServer();
        $response = $this->runApp('PATCH', "/servers/$id", $config);
        $this->assertResponse(200, $response);

        $json = json_decode($response->getBody(), true);
        $this->assertEquals('newpass', $json['pass']);
        $this->assertEquals('admin', $json['admin']);
    }


    public function testGetLog()
    {
        $id = $this->createServer();
        $response = $this->runApp('POST', "/servers/$id/start");
        $this->assertResponse(200, $response);

        $response = $this->runApp('GET', "/servers/$id/logs");

        $this->assertResponse(200, $response);
        $json = json_decode($response->getBody(), true);
        $this->assertRegExp("/LFS/", $json['logs']);
    }

    public function testGetStats()
    {
        $this->markTestIncomplete();

        $id = $this->createServer();
        $response = $this->runApp('POST', "/servers/$id/start");
        $this->assertResponse(200, $response);


        $response = $this->runApp('GET', "/servers/$id/stats");

        $this->assertResponse(200, $response);
        $json = json_decode($response->getBody(), true);
        $this->assertRegExp("/lislon test/", $json['host']);
    }

//    public function testDoubleStartServer()
//    {
//        $id = $this->createServer();
//        $response = $this->runApp('POST', "/servers/$id/start");
//        $this->assertResponse(200, $response);
//
//        $response = $this->runApp('POST', "/servers/$id/start");
//        $this->assertResponse(200, $response);
//    }

    public function testShowServer()
    {
        $id = $this->createServer();
        $response = $this->runApp('GET', "/servers/$id");
        $this->assertResponse(200, $response);
        $json = json_decode($response->getBody(), true);

        $this->assertArrayHasKey('id', $json);
        $this->assertEquals('lislon test', $json['host']);
        $this->assertEquals(true, $json['pereulok']);
        $this->assertEquals('stopped', $json['state']);
        $this->assertEquals('0.6M', $json['image']);
        $this->assertEquals('test', $json['pass']);
    }

    public function testListVersions()
    {
        $response = $this->runApp('GET', "/server-images");
        $this->assertResponse(200, $response);
        $json = json_decode($response->getBody(), true);
        $this->assertGreaterThan(0, sizeof($json));
    }
}