<?php
/**
 * Created by PhpStorm.
 * User: ele
 * Date: 11/18/16
 * Time: 4:12 PM
 */

namespace Lsn\Service\Lfs;
use Docker\Context\Context;
use Docker\Docker;
use Docker\Manager\ImageManager;
use GuzzleHttp\Psr7\LazyOpenStream;
use Http\Client\Exception\HttpException;
use Lsn\Exception\LsnException;
use Lsn\Exception\LsnNotFoundException;
use Lsn\Helper\RecursiveDirCopy;
use Lsn\Helper\TempDir;
use Lsn\Helper\TempFile;
use Lsn\Service\Interfaces\ImageService;
use Psr\Http\Message\StreamInterface;

/**
 * Lists available images of servers.
 *
 * Class LfsImageService
 * @package Lsn
 */
class LfsImageService implements ImageService
{
    private $settings;

    // Hashtable with keys as API images names and id's is real names
    private $images = null;

    /**
     * @var ImageManager
     */
    private $dockerImage;

    public function __construct(Docker $docker, $dockerSettings)
    {
        $this->dockerImage = $docker->getImageManager();
        $this->settings = $dockerSettings;
    }

    /**
     * Gets list of available lfs images.
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
                "id" => $name,
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

    public function deleteAllImages()
    {
        try {
            $images = $this->dockerImage->findAll(['filters' => json_encode(['label' => ['lfs-server']])]);
            foreach ($images as /** \Docker\API\Model\ImageItem */ $image) {
                try {
                    $this->dockerImage->remove($image->getId());
                } catch (HttpException $e) {
                    if ($e->getCode() == 409) {
                        throw new LsnNotFoundException("Can't delete image with name '{$image->getId()}'. Probably containers that using this image is still exist");
                    }
                    throw $e;
                }
            }
        } catch (HttpException $e) {
            throw $e;
        }

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
     * @param string $name
     * @param StreamInterface $stream
     * @param string $contentType Mime Content type (application/gzip)
     * @return array
     * @throws LsnException
     * @throws \Exception
     */
    public function createImage($name, StreamInterface $stream, $contentType)
    {
        $imageId = 'lfs-dedi-'.strtolower(preg_replace('/[^\w]/', '', $name));

        $dockerTempDir = new TempDir("lfs-image");
        if (!@mkdir("{$dockerTempDir->getPath()}/lfs")) {
            throw new LsnException("Cannot create {$dockerTempDir->getPath()}/lfs directory");
        }

        // unpack incoming tgz to /lfs directory
        $inTgzFile = $this->saveStreamToFile($stream, $contentType);

        $this->unpackTgzToDirectory($inTgzFile, $dockerTempDir);
        $this->copyDockerFileToDirectory($dockerTempDir);


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
                if ($id != '<none>') {
                    $name = $image->getLabels()->name;
                    $this->images[$name] = $id;
                }
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
    private function saveStreamToFile(StreamInterface $stream, $contentType)
    {
        if ($contentType == 'application/zip') {
            $extension = '.zip';
        } else {
            $extension = ".tgz";
        }
        $inTgzFile = new TempFile(null, $extension);
        $writeStream = new LazyOpenStream($inTgzFile->getPath(), 'w');
        \GuzzleHttp\Psr7\copy_to_stream($stream, $writeStream);
        return $inTgzFile;
    }

    /**
     * @param $inTgzFile
     * @param $dockerTempDir
     * @throws LsnException
     */
    private function unpackTgzToDirectory(TempFile $inTgzFile, TempDir $dockerTempDir)
    {
        if (preg_match('/zip$/', $inTgzFile->getPath())) {
            $zip = new \ZipArchive;
            if ($zip->open($inTgzFile->getPath()) === TRUE) {
                $zip->extractTo($dockerTempDir->getPath()."/lfs");
                $zip->close();
            } else {
                throw new LsnException("Failed to unzip {$inTgzFile->getPath()}");
            }
        } else {
            $inPharData = new \PharData($inTgzFile->getPath());
            $inPharData->extractTo("{$dockerTempDir->getPath()}/lfs");
        }
        $this->validateArchive($dockerTempDir);
    }

    /**
     * @param $dockerTempDir
     */
    private function copyDockerFileToDirectory($dockerTempDir)
    {
        RecursiveDirCopy::copy($this->settings['dockerfiles_path'].'/lfs-template/', $dockerTempDir->getPath());
    }

    /**
     * Checks if folder contain LFS server distribution, otherwise throws exception
     * @param TempDir $dockerTempDir
     * @throws LsnException
     */
    private function validateArchive(TempDir $dockerTempDir)
    {
        $filename = "{$dockerTempDir->getPath()}/lfs/DCon.exe";
        if (!file_exists($filename)) {
            throw new LsnException("Archive should contain DCon.exe file");
        }
        $filetype = system("file -- " . escapeshellarg($filename));
        if (!preg_match('/PE32\+? executable/', $filetype)) {
            throw new LsnException("DCon.exe is not PE32 executable, but '$filetype'");
        }
        if (!preg_match('/80386/', $filetype)) {
            throw new LsnException("DCon.exe is not 32 bit executable ('$filetype')");
        }
    }
}