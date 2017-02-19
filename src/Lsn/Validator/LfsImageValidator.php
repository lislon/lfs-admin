<?php
/**
 * Created by PhpStorm.
 * User: ele
 * Date: 2/19/17
 * Time: 11:24 AM
 */

namespace Lsn\Validator;


use Lsn\Exception\LsnException;

class LfsImageValidator implements ValidatorInterface
{
    /**
     * Checks if folder contain LFS server distribution, otherwise throws exception
     * @param string $path
     * @throws LsnException
     */
    public function validate($path)
    {
        $filename = $path."/DCon.exe";
        if (!file_exists($filename)) {
            throw new LsnException("Archive should contain DCon.exe file");
        }
        $fileType = system("file -- " . escapeshellarg($filename));
        if (!preg_match('/PE32\+? executable/', $fileType)) {
            throw new LsnException("DCon.exe is not PE32 executable, but '$fileType'");
        }
        if (!preg_match('/80386/', $fileType)) {
            throw new LsnException("DCon.exe is not 32 bit executable ('$fileType')");
        }
    }

}