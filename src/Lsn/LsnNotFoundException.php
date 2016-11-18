<?php
/**
 * Created by PhpStorm.
 * User: ele
 * Date: 11/18/16
 * Time: 1:41 PM
 */

namespace Lsn;


class LsnNotFoundException extends LsnException
{
    public function __construct($message)
    {
        parent::__construct($message, 404);
    }
}