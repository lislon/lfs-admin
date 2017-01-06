<?php

namespace Tests\Functional;
use GuzzleHttp\Psr7\LazyOpenStream;
use Tests\Helper\TempFilesMixin;

/**
 * Created by PhpStorm.
 * User: ele
 * Date: 12/29/16
 * Time: 3:22 PM
 */
class InsimImageHttpServiceTest extends BaseTestCase
{
    const IMAGE_NAME = 'test-lison-insim';

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
        $baseTestCase->runApp('POST', '/insim-images/'.self::IMAGE_NAME, $body, 'application/zip');
        return self::IMAGE_NAME;
    }

    private static function getTestImageArchive()
    {
        return new LazyOpenStream(__DIR__."/_fixtures/DummyLislonInsim/LislonInsim.zip", "r");
    }

    public function testCreateImage()
    {
        $body = self::getTestImageArchive();

        $response = $this->runApp('POST', '/insim-images/' . self::IMAGE_NAME, $body, 'application/zip');
        $this->assertResponse(200, $response);
        $this->imageName = self::IMAGE_NAME;
    }

    public function testCreateImageWithZip()
    {
        $body = self::getTestImageArchive();

        $response = $this->runApp('POST', '/insim-images/' . self::IMAGE_NAME, $body, 'application/zip');
        $this->assertResponse(200, $response);
    }


    public function testDeleteImage()
    {
        self::createTestImage($this);
        $response = $this->runApp('DELETE', '/insim-images/' . self::IMAGE_NAME);
        $this->assertResponse(204, $response);
    }

    public function testListImages()
    {
        self::createTestImage($this);
        $response = $this->runApp('GET', "/insim-images");
        $this->assertResponse(200, $response);
        $json = json_decode($response->getBody(), true);
        $this->assertGreaterThan(0, sizeof($json));
        $this->assertArrayHasKey('id', $json[0]);
        $this->assertArrayHasKey('name', $json[0]);
    }

}