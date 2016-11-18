<?php
/**
 * Created by PhpStorm.
 * User: ele
 * Date: 11/18/16
 * Time: 4:12 PM
 */

namespace Lsn;

/**
 * Lists available images of servers.
 *
 * Class LfsImageService
 * @package Lsn
 */
class LfsImageService
{
    private $lfsBasePath;


    public function __construct($dockerSettings)
    {
        $this->lfsBasePath = $dockerSettings['buildPath']."/lfsdata";
    }

    public function getImages()
    {
        $versions = [];

        foreach (new \DirectoryIterator($this->lfsBasePath) as $path) {
            if ($path->isDir() && !$path->isDot() && !$path->isFile() && file_exists($path->getPathname()."/setup.cfg")) {
                $versions[] = $path->getFilename();
            }
        }
        return $versions;
    }
}