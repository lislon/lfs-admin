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
        $result = [];
        foreach ($this->images as $name => $id) {
            $result[] = [
                "id" => $id,
                "name" => $name
            ];
        }
        return $result;
    }


    /**
     * Returns image id by user-friendly name
     *
     * @param $name
     * @return string
     * @throws LsnNotFoundException
     */
    public function getImageId($name)
    {
        if ($this->images == null) {
            $this->loadImages();
        }
        if (!isset($this->images[$name])) {
            throw new LsnNotFoundException("Image with name '$name' is not found");
        }
        return $this->images[$name];
    }

    public function getImageName($imageId)
    {
        if ($this->images == null) {
            $this->loadImages();
        }
        $imageName = array_search($imageId, $this->images);
        if ($imageName === false) {
            throw new LsnNotFoundException("Image with id '$imageId' is not found");
        }
        return $imageName;
    }

    /**
     * Deletes image from docker
     *
     * @param $name
     * @throws LsnNotFoundException
     */
    public function deleteImage($name)
    {
        try {
            // TODO: Add check for containers
            $this->dockerImage->remove($this->getImageId($name));
        } catch (HttpException $e) {
            if ($e->getCode() == 409) {
                throw new LsnNotFoundException("Can't delete image with name '$name'. Probably containers that using this image is still exist");
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
        $imageId = 'lfs-'.strtolower(trim(preg_replace('/[^a-zA-Q0-9_]/', '-', $name), "_"));

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
            't' => $imageId,
            'labels' => json_encode(['lfs-server' => 'yes', 'name' => $name])
            ]);

        return [
            'success' => array_map(function($buildInfo) {
                return $buildInfo->getStream();
            }, $result)
        ];
    }

    private function loadImages()
    {
        $this->images = [];
        $images = $this->dockerImage->findAll(['filters' => json_encode(['label' => ['lfs-server']])]);
        foreach ($images as /** \Docker\API\Model\ImageItem */ $image) {
            if (isset($image->getLabels()->name)) {
                $id = explode(":", $image->getRepoTags()[0])[0];
                $name = $image->getLabels()->name;
                $this->images[$name] = $id;
            }
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

        return isset($this->images[$imageName]);
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
}