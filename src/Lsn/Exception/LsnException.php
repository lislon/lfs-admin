<?php
/**
 * Created by PhpStorm.
 * User: ele
 * Date: 11/15/16
 * Time: 1:09 PM
 */

namespace Lsn\Exception;


class LsnException extends \Exception {

    public function __construct($message, $code = 400)
    {
        parent::__construct($message, $code);
    }
}