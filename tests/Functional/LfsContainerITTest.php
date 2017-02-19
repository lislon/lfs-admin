<?php

namespace Tests\Functional;

class LfsContainerITTest extends BaseTestCase
{
    private $serverId = null;
    private $imageCreated = null;

    const serverTemplate = [
        'port' => 58999,
        'image' => LfsImageITTest::IMAGE_NAME,
        'host' => 'lislon test',
        'usemaster' => 'no',
        'admin' => 'admin',
        'pass' => 'test',
    ];


    public static function setUpBeforeClass()
    {
        self::getApp()->getContainer()->get('lfsServer')->stopAllTestContainers();
    }

    public static function tearDownAfterClass()
    {
        self::getApp()->getContainer()->get('lfsServer')->deleteAllTestContainers();
    }

    public function setUp()
    {
        if ($this->imageCreated == null) {
            // ensure we have build image
            LfsImageITTest::createTestImage($this);
            $this->imageCreated = true;
        }
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

    public function testDeleteNotExistence()
    {
        $response = $this->runApp('DELETE', '/servers/not-existed', self::serverTemplate);
        $this->assertResponse(404, $response);
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

        for ($i = 0; $i < 10; $i++) {
            sleep(1);
            $response = $this->runApp('GET', "/servers/$id/logs");
            if ($response->getStatusCode() != 404) {
                break;
            }
        }

        $this->assertResponse(200, $response);
        $json = json_decode($response->getBody(), true);
        $this->assertRegExp("/Dummy/", $json['logs']);
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
        $this->assertEquals('stopped', $json['state']);
        $this->assertEquals(LfsImageITTest::IMAGE_NAME, $json['image']);
        $this->assertEquals('test', $json['pass']);
    }

}