<?php

namespace Tests\Functional;
use Lsn\Helper\TempDir;
use Naucon\File\File;
use Slim\Http\RequestBody;

/**
 * Created by PhpStorm.
 * User: ele
 * Date: 11/22/16
 * Time: 5:13 PM
 */
class LfsImageHttpServiceTest extends BaseTestCase
{
    const IMAGE_NAME = 'lfs-test';

    private $imageName = null;

    public static function createTestImage(BaseTestCase $baseTestCase)
    {
        $body = self::getTestImageArchive();
        $baseTestCase->runApp('POST', '/server-images/lfs-test', $body, 'application/gzip');
        return self::IMAGE_NAME;
    }

    private static function getTestImageArchive()
    {
        // create dummy image
        $tempDir = new TempDir("lfs-test-image");
        (new File($tempDir->getPath()."/DCon.exe"))->createNewFile();
        // make sure image will be rebuilt
        file_put_contents($tempDir->getPath()."/DCon.exe", "");
        file_put_contents($tempDir->getPath()."/test.txt", "This is test\n");
        file_put_contents($tempDir->getPath()."/log.log", "This is test\n");


        $tempDir4Tar = new TempDir();

        $tarArchive = "{$tempDir4Tar->getPath()}/archive.tgz";
        $pharData = new \PharData($tarArchive);
        $pharData->buildFromDirectory($tempDir->getPath());
        $gzFile = $pharData->compress(\Phar::GZ);
        $body = new RequestBody();
        $body->write(file_get_contents($gzFile->getPath()));
        $body->rewind();
        return $body;
    }

    public function testCreateImage()
    {
        $body = self::getTestImageArchive();

        $response = $this->runApp('POST', '/server-images/' . self::IMAGE_NAME, $body, 'application/gzip');
        $json = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('logs', $json);
        $this->imageName = 'test';
    }

    public function testDeleteImage()
    {
        self::createTestImage($this);
        $response = $this->runApp('DELETE', '/server-images/' . self::IMAGE_NAME);
        $this->assertResponse(204, $response);
    }

    public function testListVersions()
    {
        self::createTestImage($this);
        $response = $this->runApp('GET', "/server-images");
        $this->assertResponse(200, $response);
        $json = json_decode($response->getBody(), true);
        $this->assertGreaterThan(0, sizeof($json));
    }

}