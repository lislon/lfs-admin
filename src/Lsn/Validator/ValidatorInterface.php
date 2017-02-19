<?php
/**
 * Created by PhpStorm.
 * User: ele
 * Date: 2/19/17
 * Time: 11:22 AM
 */

namespace Lsn\Validator;

use Lsn\Exception\LsnException;

interface ValidatorInterface
{
    /**
     * Validates archive before baking it into image.
     *
     * @param string $path Path to unpacked archive with LFS/insim directory.
     * @throws LsnException when directory is not valid.
     */
    function validate($path);

}