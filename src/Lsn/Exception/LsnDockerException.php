<?php
/**
 * Created by PhpStorm.
 * User: ele
 * Date: 11/16/16
 * Time: 12:16 PM
 */

namespace Lsn\Exception;

use Http\Client\Exception\HttpException;
use Lsn\Exception\LsnException;
use Psr\Http\Message\RequestInterface;

/**
 * Exception with additional details for debugging
 *
 * @package Lsn
 */
class LsnDockerException extends LsnException
{
    private $request;

    public function __construct($message, HttpException $exception)
    {
        $this->request = $exception->getRequest();
        parent::__construct($message);
    }
    
    public function getDetailsMessage()
    {
        return "{$this->request->getUri()}\n{$this->request->getBody()}";
    }

}