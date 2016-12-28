<?php

namespace Tests\Functional;
use GuzzleHttp\Psr7\LazyOpenStream;
use Lsn\Helper\TempDir;
use Lsn\Helper\TempFile;
use Naucon\File\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Slim\Http\RequestBody;
use Tests\Helper\TempFilesMixin;

/**
 * Created by PhpStorm.
 * User: ele
 * Date: 11/22/16
 * Time: 5:13 PM
 */
class LfsImageHttpServiceTest extends BaseTestCase
{

    const IMAGE_NAME = 'test-0.6Q';

    private $imageName = null;
    /**
     * @var TempFilesMixin
     */
    private static $tempFiles;

    public static function setUpBeforeClass()
    {
        if (!self::$tempFiles) {
            self::$tempFiles = new TempFilesMixin();
        }
    }


    protected function tearDown()
    {
        self::$tempFiles->cleanTempFiles();
    }

    public static function createTestImage(BaseTestCase $baseTestCase)
    {
        $body = self::getTestImageArchive();
        $baseTestCase->runApp('POST', '/server-images/'.self::IMAGE_NAME, $body, 'application/zip');
        return self::IMAGE_NAME;
    }

    private static function getTestImageArchive()
    {
        return new LazyOpenStream(__DIR__."/_fixtures/LfsDummyImage.zip", "r");
    }

    public function testCreateImage()
    {
        $body = self::getTestImageArchive();

        $response = $this->runApp('POST', '/server-images/' . self::IMAGE_NAME, $body, 'application/zip');
        $this->assertResponse(200, $response);
        $this->imageName = self::IMAGE_NAME;
    }

    public function testCreateImageWithZip()
    {
        $body = self::getTestImageArchive();

        $response = $this->runApp('POST', '/server-images/' . self::IMAGE_NAME, $body, 'application/zip');
        $this->assertResponse(200, $response);
    }


    public function testDeleteImage()
    {
        self::createTestImage($this);
        $response = $this->runApp('DELETE', '/server-images/' . self::IMAGE_NAME);
        $this->assertResponse(204, $response);
    }

    public function testListImages()
    {
        self::createTestImage($this);
        $response = $this->runApp('GET', "/server-images");
        $this->assertResponse(200, $response);
        $json = json_decode($response->getBody(), true);
        $this->assertGreaterThan(0, sizeof($json));
        $this->assertArrayHasKey('id', $json[0]);
        $this->assertArrayHasKey('name', $json[0]);
    }

}