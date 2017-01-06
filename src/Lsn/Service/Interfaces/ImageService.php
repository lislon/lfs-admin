<?php
/**
 * Created by PhpStorm.
 * User: ele
 * Date: 1/6/17
 * Time: 1:46 PM
 */

namespace Lsn\Service\Interfaces;


use Lsn\Exception\LsnException;
use Lsn\Exception\LsnNotFoundException;
use Psr\Http\Message\StreamInterface;

interface ImageService
{

    /**
     * Gets list of available images.
     *
     * @return string[] List of image
     */
    function getImages();

    /**
     * Deletes image from docker
     *
     * @param $name
     * @throws LsnNotFoundException
     */
    function deleteImage($name);

    /**
     *
     * @param string $name
     * @param StreamInterface $stream
     * @param string $contentType Mime Content type (application/gzip)
     * @return array
     * @throws LsnException
     * @throws \Exception
     */
    function createImage($name, StreamInterface $stream, $contentType);
}