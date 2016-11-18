<?php
/**
 * Created by PhpStorm.
 * User: ele
 * Date: 11/18/16
 * Time: 4:12 PM
 */

namespace Lsn;

/**
 * Lists available versions of servers.
 *
 * Class LfsVersionService
 * @package Lsn
 */
class LfsVersionService
{
    private $lfsBasePath;


    public function __construct($dockerSettings)
    {
        $this->lfsBasePath = $dockerSettings['buildPath']."/lfsdata";
    }

    public function getServerVersions()
    {
        $versions = [];

        foreach (new \DirectoryIterator($this->lfsBasePath) as $path) {
            if ($path->isDir()) {
                $versions[] = $path->getFilename();
            }
        }
        return $versions;
    }
}