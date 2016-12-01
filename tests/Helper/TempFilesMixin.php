<?php

namespace Tests\Helper;
use Lsn\Helper\TempDir;
use Lsn\Helper\TempFile;

/**
 * Created by PhpStorm.
 * User: ele
 * Date: 11/29/16
 * Time: 1:32 AM
 */
class TempFilesMixin
{
    private $tempFiles = [];

    public function tempDir($prefix = null)
    {
        $tempDir = new TempDir($prefix);
        $this->tempFiles[] = $tempDir;
        return $tempDir;
    }

    public function tempFile($prefix = null, $postfix = null)
    {
        $tempFile = new TempFile($prefix, $postfix);
        $this->tempFiles[] = $tempFile;
        return $tempFile;
    }

    public function cleanTempFiles()
    {
        $this->tempFiles = [];
    }
}