<?php
/**
 * Created by PhpStorm.
 * User: ele
 * Date: 11/18/16
 * Time: 4:12 PM
 */

namespace Lsn;
use Docker\Context\Context;
use Docker\Docker;
use Docker\Manager\ImageManager;
use Docker\Stream\BuildStream;
use GuzzleHttp\Psr7\LazyOpenStream;
use GuzzleHttp\Psr7\Stream;
use Http\Client\Exception\HttpException;
use Lsn\Exception\LsnException;
use Lsn\Exception\LsnNotFoundException;
use Lsn\Helper\RecursiveDirCopy;
use Lsn\Helper\TempDir;
use Lsn\Helper\TempFile;
use Psr\Http\Message\StreamInterface;

/**
 * Lists available images of servers.
 *
 * Class LfsImageService
 * @package Lsn
 */
class LfsImageService
{
    private $lfsBasePath;
    private $images = null;

    /**
     * @var ImageManager
     */
    private $dockerImage;

    public function __construct(Docker $docker, $dockerSettings)
    {
        $this->dockerImage = $docker->getImageManager();
        $this->lfsBasePath = $dockerSettings['buildPath']."/lfsdata";
    }

    /**
     * Gets list of availiable lfs images.
     *
     * @return string[] List of image
     */
    public function getImages()
    {
        if ($this->images == null) {
            $this->loadImages();
        }
        return $this->images;
    }

    public function deleteImage($name)
    {
        if (!$this->validateImageName($name)) {
            throw new LsnException("Image name must starts with lfs- prefix");
        }
        try {
            $this->dockerImage->remove($name);
        } catch (HttpException $e) {
            if ($e->getCode() == 404) {
                throw new LsnNotFoundException("Image with name '$name' is not found");
            }
            throw $e;
        }
    }

    /**
     *
     * @param $name
     * @param $stream StreamInterface
     * @return array
     * @throws LsnException
     * @throws \Exception
     */
    public function createImage($name, StreamInterface $stream)
    {
        if (!$this->validateImageName($name)) {
            throw new LsnException("Image name must starts with lfs- prefix");
        }

        $dockerTempDir = new TempDir("lfs-image");
        if (!@mkdir("{$dockerTempDir->getPath()}/lfs")) {
            throw new LsnException("Cannot create {$dockerTempDir->getPath()}/lfs directory");
        }

        // unpack incoming tgz to /lfs directory
        $inTgzFile = $this->saveStreamToFile($stream);

        $this->unpackTgzToDirectory($inTgzFile, $dockerTempDir);
        $this->copyDockerFileToDirectory($dockerTempDir);

//         now pack it back to archive to send for docker
//        $this->packDirectoryToTgz($dockerTempDir);

        $context = new Context($dockerTempDir->getPath());
        $inputStream = $context->toStream();
        $result = $this->dockerImage->build($inputStream, [
            't' => "$name",
            'labels' => json_encode(['lfs-server' => 'yes'])
            ]);

        return [
            'logs' => array_map(function($buildInfo) {
                return $buildInfo->getStream();
            }, $result)
        ];
    }

    private function loadImages()
    {
        $this->images = [];
        $images = $this->dockerImage->findAll(['filters' => json_encode(['label' => ['lfs-server']])]);
        foreach ($images as /** \Docker\API\Model\ImageItem */ $image) {
            $this->images[] = explode(":", $image->getRepoTags()[0])[0];
        }
    }

    /**
     * Checks whether docker image with $imageName exists.
     *
     * @param $imageName string
     * @return bool
     */
    public function hasImage($imageName)
    {
        if ($this->images == null) {
            $this->loadImages();
        }

        return array_search($imageName, $this->images) !== false;
    }

    private function validateImageName($name)
    {
        return preg_match('/^[\w-_\.]+$/', $name) !== false;
    }

    /**
     * @param StreamInterface $stream
     * @return TempFile
     */
    private function saveStreamToFile(StreamInterface $stream)
    {
        $inTgzFile = new TempFile(null, '.tgz');
        $writeStream = new LazyOpenStream($inTgzFile->getPath(), 'w');
        \GuzzleHttp\Psr7\copy_to_stream($stream, $writeStream);
        return $inTgzFile;
    }

    /**
     * @param $inTgzFile
     * @param $dockerTempDir
     * @throws LsnException
     */
    private function unpackTgzToDirectory($inTgzFile, $dockerTempDir)
    {
        $inPharData = new \PharData($inTgzFile->getPath());
        $inPharData->extractTo("{$dockerTempDir->getPath()}/lfs");
        if (!file_exists("{$dockerTempDir->getPath()}/lfs/DCon.exe")) {
            throw new LsnException("Archive should contain DCon.exe file");
        }
    }

    /**
     * @param $dockerTempDir
     */
    private function copyDockerFileToDirectory($dockerTempDir)
    {
        RecursiveDirCopy::copy(realpath(__DIR__ . '/../../dockerfiles/lfs-template/'), $dockerTempDir->getPath());
    }

    /**
     * @param $dockerTempDir
     */
    private function packDirectoryToTgz($dockerTempDir)
    {
        $outTgzFile = new TempFile(null, ".tgz");
        $outPharData = new \PharData($outTgzFile->getPath());
        $outPharData->buildFromDirectory($dockerTempDir->getPath());
        return $outPharData;
    }
}