<?php
/**
 * Created by PhpStorm.
 * User: ele
 * Date: 1/6/17
 * Time: 2:19 PM
 */

namespace Lsn\Helper;


use Docker\API\Model\BuildInfo;
use Docker\Context\Context;
use Docker\Manager\ImageManager;
use GuzzleHttp\Psr7\LazyOpenStream;
use Http\Client\Exception\HttpException;
use Lsn\Exception\LsnException;
use Lsn\Exception\LsnNotFoundException;
use Lsn\Validator\ValidatorInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Copies the files from dockerfiles template directories, then overrides it with user-uploaded zip file,
 * validates and builds an docker image.
 *
 * Class ImageCreatorHelper
 * @package Lsn\Helper
 */
class DockerImageManager
{
    // Hashtable with keys as API images names and id's is real names
    private $images = null;
    /**
     * @var string alphanumeric name of image. Should be same across images of same type.
     */
    private $labelTag;

    /**
     * @var string Path to directory containing Dockerfile
     */
    private $dockerBuildPath;

    /**
     * @var ValidatorInterface Callback to validate contents of archive before creation of image.
     */
    private $imageValidator;

    /**
     * @var ImageManager
     */
    private $dockerImage;

    private $extractSubDirectory = 'content';

    /**
     * ImageCreatorHelper constructor.
     *
     * @param $labelTag string label to mark and filter images
     * @param string $dockerBuildPath Path to directory containing Dockerfile
     * @param ImageManager $dockerImage
     */
    public function __construct($labelTag, $dockerBuildPath, ImageManager $dockerImage)
    {
        $this->labelTag = $labelTag;
        $this->dockerImage = $dockerImage;
        $this->dockerBuildPath = $dockerBuildPath;
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


    private function loadImages()
    {
        $this->images = [];
        $images = $this->dockerImage->findAll(['filters' => json_encode(['label' => [$this->labelTag]])]);
        foreach ($images as /** \Docker\API\Model\ImageItem */
                 $image) {
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
                $zip->extractTo($dockerTempDir->getPath() . "/{$this->extractSubDirectory}");
                $zip->close();
            } else {
                throw new LsnException("Failed to unzip {$inTgzFile->getPath()}");
            }
        } else {
            $inPharData = new \PharData($inTgzFile->getPath());
            $inPharData->extractTo("{$dockerTempDir->getPath()}/{$this->extractSubDirectory}");
        }
        if ($this->imageValidator != null) {
            $this->imageValidator->validate("{$dockerTempDir->getPath()}/{$this->extractSubDirectory}");
        }
    }

    public function buildStaticImage($name)
    {
        $imageId = strtolower(preg_replace('/[^\w]/', '', $name));
        $context = new Context($this->dockerBuildPath);
        $inputStream = $context->toStream();
        $result = $this->dockerImage->build($inputStream, [
            't' => $imageId,
            'labels' => json_encode([$this->labelTag => 'yes', 'name' => $name])
        ]);

        $result = array_map(function (BuildInfo $buildInfo) {
            return $buildInfo->getStream();
        }, $result);
        return $result;

    }

    /**
     *
     * @param string $name name of image tag for identification, eg. lfs-06b
     * @param StreamInterface $stream An zip/gzip archive containting files to override docker base images
     * @param $contentType string stream type: "zip" or "wip"
     * @return array logs of building process
     * @throws LsnException
     */
    public function buildImage($name, StreamInterface $stream, $contentType)
    {
        // generate image id for docker. name goes to tag.
        $imageId = $this->labelTag . '-' . strtolower(preg_replace('/[^\w]/', '', $name));
        $dockerTempDir = new TempDir("docker-build-image");

        if (!@mkdir("{$dockerTempDir->getPath()}/".$this->extractSubDirectory)) {
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
            'labels' => json_encode([$this->labelTag => 'yes', 'name' => $name])
        ]);

        foreach ($result as $buildInfo) {
            if ($buildInfo->getError()) {
                $log = implode("\n", array_map(function ($bInfo) {
                    return $bInfo->getStream();
                }, $result));
                throw new LsnException("Error while building image: \n". $log);
            }
        }

        return $result;
    }


    /**
     * Copy DockerFile from project source to temp directory before building image.
     * @param $dockerTempDir
     */
    private function copyDockerFileToDirectory($dockerTempDir)
    {
        RecursiveDirCopy::copy($this->dockerBuildPath, $dockerTempDir->getPath());
    }

    /**
     * @param $validator ValidatorInterface Image validator
     */
    public function setImageValidator(ValidatorInterface $validator)
    {
        $this->imageValidator = $validator;
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

    public function deleteAllImages()
    {
        try {
            $images = $this->dockerImage->findAll(['filters' => json_encode(['label' => [$this->labelTag]])]);
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

}