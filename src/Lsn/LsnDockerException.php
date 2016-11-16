<?php
/**
 * Created by PhpStorm.
 * User: ele
 * Date: 11/16/16
 * Time: 12:16 PM
 */

namespace Lsn;

use Psr\Http\Message\RequestInterface;

/**
 * Exception with additional details for debugging
 *
 * @package Lsn
 */
class LsnDockerException extends LsnException
{
    private $request;

    public function __construct($message, RequestInterface $request)
    {
        $this->request = $request;
        parent::__construct($message);
    }
    
    public function getDetailsMessage()
    {
        return "{$this->request->getUri()}\n{$this->request->getBody()}";
    }

}