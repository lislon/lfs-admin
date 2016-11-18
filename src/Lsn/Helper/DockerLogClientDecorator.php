<?php
/**
 * Created by PhpStorm.
 * User: ele
 * Date: 11/16/16
 * Time: 12:59 AM
 */

namespace Lsn\Helper;


use Docker\DockerClient;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;

/**
 * Logs requests to docker.
 *
 * Class DockerLogClientDecorator
 * @package Lsn\Helper
 */
class DockerLogClientDecorator extends DockerClient
{
    private $logger;
    private $client;

    public function __construct(DockerClient $client, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->client = $client;
    }

    /**
     * (@inheritdoc}
     */
    public function sendRequest(RequestInterface $request)
    {
        $this->logger->info("docker {$request->getUri()} {$request->getBody()}");
        return $this->client->sendRequest($request);
    }
}