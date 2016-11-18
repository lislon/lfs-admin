<?php
/**
 * Created by PhpStorm.
 * User: ele
 * Date: 11/18/16
 * Time: 1:41 PM
 */

namespace Lsn\Exception;


use Lsn\Exception\LsnException;

class LsnNotFoundException extends LsnException
{
    public function __construct($message)
    {
        parent::__construct($message, 404);
    }
}