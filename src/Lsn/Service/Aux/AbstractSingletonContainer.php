<?php
/**
 * Created by PhpStorm.
 * User: ele
 * Date: 1/4/17
 * Time: 3:12 PM
 */

namespace Lsn\Service\Aux;


use Docker\API\Model\ContainerConfig;
use Docker\API\Model\HostConfig;
use Docker\Docker;
use Http\Client\Exception\HttpException;
use Lsn\Exception\LsnDockerException;
use Lsn\Exception\LsnException;

abstract class AbstractSingletonContainer
{
    private $env;
    private $portExpose;

    private $docker;
    private $isRunning = null;
    private $containerName;
    private $imageName;

    /**
     * XServerService constructor.
     * @param $docker
     */
    protected function __construct(Docker $docker, $imageName, $containerName, $env = array(), $portExpose = null)
    {
        $this->docker = $docker;
        $this->containerName = $containerName;
        $this->imageName = $imageName;
        $this->env = $env;
        $this->portExpose = $portExpose;
    }

    /**
     * Ensures that X11 container exists, and then start it if ti's stopped
     *
     * @throws LsnException
     */
    public function runIfStopped()
    {
        if ($this->isRunning) {
            return;
        }
        try {

            $existingContainer = $this->getExistingContainer();
            if ($existingContainer) {
                $containerState = $existingContainer->getState();
                if (!$containerState->getRunning()) {
                    $this->docker->getContainerManager()->start($this->containerName);
                }
            } else {
                $this->createContainer();
                $this->docker->getContainerManager()->start($this->containerName);
            }
            $this->isRunning = true;
        } catch (HttpException $e) {
            throw new LsnException("Failed to create {$this->containerName}: " . $e->getMessage());
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
            return $this->docker->getContainerManager()->find($this->containerName);
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

            $containerConfig = new ContainerConfig();
            $containerConfig->setImage($this->imageName);
            $containerConfig->setEnv($this->env);

            $hostConfig = new HostConfig();
            $hostConfig->setPortBindings([
                "{$this->portExpose}" => [["HostPort" => "{$this->portExpose}"]],
            ]);

            $containerConfig->setHostConfig($hostConfig);
            $containerConfig->setExposedPorts([
                "{$this->portExpose}" => new \ArrayObject(),
            ]);

            $container = $containerManager->create($containerConfig, ['name' => $this->containerName]);

            return $container->getId();
        } catch (HttpException $e) {
            throw new LsnDockerException($e->getMessage(), $e);

        }
    }
}