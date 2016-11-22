<?php
/**
 * Created by PhpStorm.
 * User: ele
 * Date: 11/22/16
 * Time: 1:33 PM
 */

namespace Lsn\Helper;


use Naucon\File\File;

class TempDir
{
    private $tempDir = null;
    private $prefix;

    /**
     * TempDir constructor.
     * @param $prefix
     */
    public function __construct($prefix = null)
    {
        $this->prefix = $prefix;
    }


    public function getPath()
    {
        if ($this->tempDir == null) {
            $this->tempDir = tempnam(sys_get_temp_dir(), $this->prefix);
            if (file_exists($this->tempDir)) {
                unlink($this->tempDir);
            }
            @mkdir($this->tempDir);
            if (!is_dir($this->tempDir)) {
                throw new \Exception("Failed to create temporary directory '$this->tempDir");
            }
        }
        return $this->tempDir;
    }


    public function __destruct()
    {
        $this->destroy();
    }

    public function destroy()
    {
        if ($this->tempDir) {
            (new File($this->tempDir))->deleteAll();
        }
    }
}