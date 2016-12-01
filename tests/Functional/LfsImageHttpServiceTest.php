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


    /**
     * @return TempDir
     * @throws \Exception
     * @throws \Naucon\File\Exception\FileException
     */
    private static function prepareFolderWithImage()
    {
        if (!self::$tempFiles) {
            self::$tempFiles = new TempFilesMixin();
        }
        // create dummy image
        $tempDir = self::$tempFiles->tempDir("lfs-test-image");
        (new File($tempDir->getPath() . "/DCon.exe"))->createNewFile();
        // make sure image will be rebuilt
        file_put_contents($tempDir->getPath() . "/DCon.exe", "");
        file_put_contents($tempDir->getPath() . "/test.txt", "This is test\n");
        file_put_contents($tempDir->getPath() . "/log.log", "This is test\n");
        mkdir($tempDir->getPath()."/data");
        file_put_contents($tempDir->getPath() . "/data/test.txt", "This is test inside data\n");
        return $tempDir;
    }

    public static function createTestImage(BaseTestCase $baseTestCase)
    {
        $body = self::getTestImageArchive();
        $baseTestCase->runApp('POST', '/server-images/'.self::IMAGE_NAME, $body, 'application/gzip');
        return self::IMAGE_NAME;
    }

    private static function getTestImageArchive()
    {
        $tempDir = self::prepareFolderWithImage();
        $tempDir4Tar = self::$tempFiles->tempDir();

        $tarArchive = "{$tempDir4Tar->getPath()}/archive.tgz";
        $pharData = new \PharData($tarArchive);
        $pharData->buildFromDirectory($tempDir->getPath());
        $gzFile = $pharData->compress(\Phar::GZ);
        $body = new LazyOpenStream($gzFile->getPath(), "r");
//        $body = new RequestBody();
//        $body->write(file_get_contents($gzFile->getPath()));
//        $body->rewind();
        return $body;
    }

    public function testCreateImage()
    {
        $body = self::getTestImageArchive();

        $response = $this->runApp('POST', '/server-images/' . self::IMAGE_NAME, $body, 'application/gzip');
        $this->assertResponse(200, $response);
        $this->imageName = self::IMAGE_NAME;
    }

    public function testCreateImageWithZip()
    {
        $tempDir = self::prepareFolderWithImage();
        $tempFile = self::$tempFiles->tempFile(null, ".zip");
        $zip = new \ZipArchive();
        $zip->open($tempFile->getPath(), \ZipArchive::CREATE);
        // Create recursive directory iterator

        /** @var SplFileInfo[] $files */
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tempDir->getPath()),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file) {
            // Skip directories (they would be added automatically)
            if (!$file->isDir()) {
                // Get real and relative path for current file
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($tempDir->getPath()) + 1);

                // Add current file to archive
                $zip->addFile($filePath, $relativePath);
            }
        }
        $zip->close();

        $lazyOpenStream = new LazyOpenStream($tempFile->getPath(), "r");

        $response = $this->runApp('POST', '/server-images/' . self::IMAGE_NAME, $lazyOpenStream, 'application/zip');
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