<?php
/**
 * Created by PhpStorm.
 * User: ele
 * Date: 11/19/16
 * Time: 9:54 PM
 */

namespace Lsn;


use Docker\API\Model\Container;
use Docker\API\Model\ContainerConnect;
use Docker\API\Model\NetworkCreateConfig;
use Docker\Docker;
use Http\Client\Exception\HttpException;

class DockerNetwork
{
    const NETWORK_NAME = "lfs_default";

    private $docker;
    private $isNetworkExists = null;

    /**
     * DockerNetwork constructor.
     */
    public function __construct(Docker $docker)
    {
        $this->docker = $docker;
    }
    
    public function attachToNetwork($containerId)
    {
        if (!$this->isNetworkExists) {
            $this->createNetworkIfNotExists();
        }

        $containerConnect = new ContainerConnect();
        $containerConnect->setContainer($containerId);
        $this->docker->getNetworkManager()->connect(self::NETWORK_NAME, $containerConnect);
    }

    private function createNetworkIfNotExists()
    {
        try {
            $network = $this->docker->getNetworkManager()->find(self::NETWORK_NAME);
        } catch (HttpException $e) {
            if ($e->getCode() != 404) {
                throw $e;
            }
            $this->createNetwork();
        }
        $this->isNetworkExists = true;
    }


    private function createNetwork()
    {
        $networkConfig = new NetworkCreateConfig();
        $networkConfig->setName(self::NETWORK_NAME);
        $networkConfig->setInternal(true);

        $this->docker->getNetworkManager()->create($networkConfig);

    }
}