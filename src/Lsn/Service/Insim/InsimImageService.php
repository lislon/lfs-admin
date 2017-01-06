<?php
/**
 * Created by PhpStorm.
 * User: ele
 * Date: 1/6/17
 * Time: 1:33 PM
 */

namespace Lsn\Service\Insim;


use Docker\Docker;
use Lsn\Service\Interfaces\ImageService;
use Psr\Http\Message\StreamInterface;

class InsimImageService implements ImageService
{
    private $docker;

    /**
     * InsimImageService constructor.
     * @param $docker
     */
    public function __construct(Docker $docker)
    {
        $this->docker = $docker;
    }

    /**
     * Gets list of available images.
     *
     * @return string[] List of image
     */
    public function getImages()
    {

    }

    /**
     * Deletes image from docker
     *
     * @param $name
     * @throws LsnNotFoundException
     */
    public function deleteImage($name)
    {

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

    }
}