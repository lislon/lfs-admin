<?php
/**
 * Created by PhpStorm.
 * User: ele
 * Date: 11/22/16
 * Time: 3:19 PM
 */

namespace Lsn\Helper;

use Lsn\Helper\TempDir;

class TempFile
{
    private $tempFile = null;
    private $tempFileOriginal = null;
    private $prefix;
    private $postfix;

        /**
     * TempDir constructor.
     * @param $prefix
     */
    public function __construct($prefix = null, $postfix = null)
    {
        $this->prefix = $prefix;
        $this->postfix = $postfix;
    }


    public function getPath()
    {
        if ($this->tempFile == null) {
            $this->tempFileOriginal = tempnam(sys_get_temp_dir(), $this->prefix);
            $this->tempFile = $this->tempFileOriginal.$this->postfix;
        }

        return $this->tempFile;
    }


    public function __destruct()
    {
        $this->destroy();
    }

    public function destroy()
    {
        if ($this->tempFileOriginal && file_exists($this->tempFileOriginal)) {
            unlink($this->tempFileOriginal);
        }
        if ($this->tempFile && file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

}