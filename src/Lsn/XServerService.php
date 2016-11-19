<?php
/**
 * Created by PhpStorm.
 * User: ele
 * Date: 11/16/16
 * Time: 11:48 AM
 */

namespace Lsn;


use Docker\API\Model\ContainerConfig;
use Docker\API\Model\HostConfig;
use Docker\Docker;
use Docker\Manager\ContainerManager;
use Http\Client\Exception\HttpException;
use Lsn\Exception\LsnDockerException;
use Lsn\Exception\LsnException;

/**
 * This class runs docker X11 Server for LFS servers.
 *
 * Class XServerService
 * @package Lsn
 */
class XServerService
{
    const CONTAINER_NAME = 'xserver';

    private $port = 5900;
    private $password = "docker";

    private $docker;
    private $isRunning = null;

    /**
     * XServerService constructor.
     * @param $docker
     */
    public function __construct(Docker $docker)
    {
        $this->docker = $docker;
    }

    public function runIfStopped()
    {
        if ($this->isRunning) {
            return;
        }
        try {
            $containerState = null;

            $existing = $this->getExistingContainer();
            if ($existing) {
                $containerState = $existing->getState();
            } else {
                $this->createContainer();
                $containerState = 'created';
            }
            if (!$containerState->getRunning()) {
                $this->docker->getContainerManager()->start(self::CONTAINER_NAME);
            }
            $this->isRunning = true;
        } catch (HttpException $e) {
            throw  new LsnException("Failed to create xserver: " . $e->getMessage());
        }
    }

    /**
     * Checks if container exists
     *
     * @return \Docker\API\Model\Container
     */
    private function getExistingContainer()
    {
        try {
            return $this->docker->getContainerManager()->find(self::CONTAINER_NAME);
        } catch (HttpException $e) {
            if ($e->getCode() == 404) {
                return null;
            }
            throw $e;
        }
    }

    private function createContainer()
    {
        try {
            $containerManager = $this->docker->getContainerManager();

            $containerConfig= new ContainerConfig();
            $containerConfig->setImage('x11server');
            $containerConfig->setEnv(["VNC_PASSWORD={$this->password}"]);

            $hostConfig = new HostConfig();
            $hostConfig->setPortBindings([
                "{$this->port}" => [["HostPort" => "{$this->port}"]],
            ]);

            $containerConfig->setHostConfig($hostConfig);
            $containerConfig->setExposedPorts([
                "{$this->port}" => new \ArrayObject(),
            ]);

            $container = $containerManager->create($containerConfig, ['name' => self::CONTAINER_NAME]);

            return $container->getId();
        } catch (HttpException $e) {
            throw new LsnDockerException($e->getMessage(), $e);
        }
    }
}