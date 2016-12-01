<?php

namespace Lsn;

use Docker\API\Model\Container;
use Docker\API\Model\ContainerInfo;
use Docker\API\Model\ContainerState;
use Docker\API\Model\HostConfig;
use Docker\API\Model\NetworkConfig;
use Docker\API\Model\NetworkCreateConfig;
use Docker\API\Model\RestartPolicy;
use Docker\Docker;
use Docker\API\Model\ContainerConfig;
use Http\Client\Common\Exception\ClientErrorException;
use Http\Client\Exception\HttpException;
use Lsn\Exception\LsnDockerException;
use Lsn\Exception\LsnException;
use Lsn\Exception\LsnNotFoundException;
use Lsn\Helper\DockerUtils;

/**
 * Class controlling creation, starting and stopping of docker containers for LFS server.
 *
 * Class LfsServerService
 * @package Lsn
 */
class LfsServerService
{
    private $docker;
    private $dockerSettings;
    private $lfsBasePath;
    private $cfgBasePath;
    private $xServer;
    private $isTesting;
    private $dockerNetwork;
    private $dockerImage;


    /**
     * LfsServerService constructor.
     * @param Docker $docker
     * @param $dockerSettings
     * @param XServerService $displayService Service for running x11 server for LFS boxes
     */
    public function __construct(Docker $docker, $dockerSettings, XServerService $displayService, $env)
    {
        $this->docker = $docker;
        $this->dockerSettings = $dockerSettings;
        $this->lfsBasePath = $dockerSettings['buildPath']."/lfsdata";
        $this->cfgBasePath = $dockerSettings['buildPath']."/lfscfg";
        $this->isTesting = $env == 'test';
        $this->xServer = $displayService;
        $this->dockerNetwork = new DockerNetwork($docker);
        $this->dockerImage = new LfsImageService($docker, $dockerSettings);
    }
    
    public function getLogs($containerId)
    {
        try {
            return ['logs' => DockerUtils::readContainerFile($this->docker, $containerId, '/lfs/log.log')];

        } catch (HttpException $e) {
            throw new LsnDockerException($e->getMessage(), $e);
        }
    }

    public function getStats($containerId)
    {
        $containerInfo = $this->get($containerId);

        try {
            if ($containerInfo['state'] != 'running') {
                throw new LsnException("Container must be running to get statistics", 409);
            }
            $statsContents = DockerUtils::readContainerFile($this->docker, $containerId, "/lfs/host{$containerInfo['port']}.txt");

            return LfsConfigParser::parseStats($statsContents);

        } catch (HttpException $e) {
            throw new LsnDockerException($e->getMessage(), $e);
        }
    }


    /**
     * Remove LFS server container
     *
     * @param $containerId
     * @throws LsnException
     */
    public function delete($containerId)
    {
        try {
            $container = $this->docker->getContainerManager()->find($containerId);
            $mapper = new DockerStateMapper();


            // Stop container if necessary
            if ($mapper->mapContainerState($container) != 'stopped') {
                throw new LsnException("Server should be stopped prior to deleting", 409);
            }

            $containerManager = $this->docker->getContainerManager();
            $containerManager->remove($containerId);

            // clean after ourself
            LfsConfigParser::cleanFiles($this->getLfsConfigPath($container));

        } catch (HttpException $e) {
            throw new LsnDockerException($e->getMessage(), $e);
        }
    }

    /**
     * Return list of server containers
     */
    public function listServers()
    {
        try {
            $stateMapper = new DockerStateMapper();
            $containerInfos = $this->findAllLfsContainers();

            return array_map(function (ContainerInfo $container) use ($stateMapper) {
                return [
                    'id' => $container->getId(),
                    'state' => $stateMapper->mapContainerInfoState($container),
                ];
            }, $containerInfos);
        } catch (HttpException $e) {
            throw new LsnDockerException($e->getMessage(), $e);
        }
    }

    public function get($containerId)
    {
        try {
            $stateMapper = new DockerStateMapper();

            $container = $this->docker->getContainerManager()->find($containerId);
            $result = [
                'id'    => $container->getId(),
                'state' => $stateMapper->mapContainerState($container),
                'pereulok' => !empty($container->getConfig()->getLabels()['lfs-pereulok']),
                'image' => $this->dockerImage->getImageName($container->getConfig()->getImage()),
            ];

            $result = array_merge(
                $result,
                LfsConfigParser::readConfig($this->getLfsConfigPath($container)));
            return $result;

        } catch (HttpException $e) {
            if ($e->getCode() == 404) {
                throw new LsnNotFoundException("Container with id = $containerId not found");
            } else {
                throw new LsnDockerException("Fail to get container $containerId", $e);
            }
        }
    }

    public function start($containerId)
    {
        try {
            $this->xServer->runIfStopped();
            $this->docker->getContainerManager()->start($containerId);
        } catch (HttpException $e) {

            // Try a bit to find a reason why server is not starting:
            // Check if we port is busy
            if ($e->getCode() == 500) {
                try {
                    $currentInfo = $this->docker->getContainerManager()->find($containerId);
                    $portBindings = (array)$currentInfo->getHostConfig()->getPortBindings();
                    $wantedPort = reset($portBindings)[0]->getHostPort();


                    $containerInfos = $this->findAllLfsContainers(['running', 'restarting']);
                    foreach ($containerInfos as $containerInfo) {
                        if (!empty($containerInfo->getPorts())) {
                            $openedPort = $containerInfo->getPorts()[0]->getPublicPort();
                            if ($openedPort == $wantedPort) {
                                throw new LsnException("Port {$wantedPort} is already binded for {$containerInfo->getId()}");
                            }
                        }
                    }

                } catch (HttpException $ignored) {
                    // we do not want to hide original exception
                }
            }
            // oh, no guess why we got 500
            throw new LsnException($e->getMessage());
        }
    }


    public function stop($containerId)
    {
        try {
            $this->docker->getContainerManager()->stop($containerId);

        } catch (HttpException $e) {
            throw new LsnException($e->getMessage(), $e);
        }
    }

    public function patch($containerId, $config)
    {
        try {
            $container = $this->docker->getContainerManager()->find($containerId);

            $basePath = $this->getLfsConfigPath($container);
            $originalConfig = LfsConfigParser::readConfig($basePath);

            if (($param = $this->getParamThatRequiresContainerRecreation($config, $originalConfig)) !== false) {
                throw new LsnException("Changing {$param} parameter require migration method", 409);
            }

            $newConfig = array_merge($originalConfig, $config);
            LfsConfigParser::writeConfig($basePath, $newConfig);
            return $newConfig;
        } catch (HttpException $e) {
            throw new LsnDockerException($e->getMessage(), $e);
        }
    }

  

    /**
     * Creates a new docker container for given server.
     *
     * @param $config
     *  ports - required
     *  image - required
     * @return string id of container
     * @throws LsnException
     */
    public function create($config)
    {
        try {
            $this->xServer->runIfStopped();

            $lfsImage = $this->dockerImage->getImageId($config['image']);
            $port = $config['port'];
            $configDir = $port . '-' . time();


            //        if ($this->isContainerExists($this->getContainerName($serverName))) {
            //            $this->delete($serverName);
            //        }

            $containerManager = $this->docker->getContainerManager();

            $containerConfig = new ContainerConfig();
            $containerConfig->setImage($lfsImage);
            $containerConfig->setWorkingDir("/lfs");
            $labels = [
                'lfs-server' => 'true',
                'conf-dir' => $configDir,
            ];

            if ($this->isTesting) {
                $labels['is-testing'] = 'yes';
            }

            $hostConfig = new HostConfig();
            $hostConfig->setPortBindings([
                "$port/tcp" => [["HostPort" => "$port"]],
                "$port/udp" => [["HostPort" => "$port"]]
            ]);
            $hostConfig->setVolumesFrom(["xserver"]);

            $restartPolicy = new RestartPolicy();
            $restartPolicy->setMaximumRetryCount(2);
            $restartPolicy->setName('unless-stopped');
            $hostConfig->setRestartPolicy($restartPolicy);

            $containerConfig->setHostConfig($hostConfig);
            $containerConfig->setExposedPorts([
                "$port/tcp" => new \ArrayObject(),
                "$port/udp" => new \ArrayObject()
            ]);

            LfsConfigParser::writeConfig("{$this->cfgBasePath}/$configDir", $config);

            $binds = ["/etc/localtime:/etc/localtime:ro"];

            foreach (['setup.cfg', 'welcome.txt', 'tracks.txt'] as $file) {
                $binds[] = "{$this->cfgBasePath}/$configDir/$file:/lfs/$file";
            }

            if (!empty($config['pereulok'])) {
                $binds[] = "{$this->lfsBasePath}/$lfsImage/launcher_lic.cf:/lfs/launcher_lic.cf:ro";
                $binds[] = "{$this->lfsBasePath}/lfspLauncher.exe:/lfs/LFSP.exe:ro";
                $labels['lfs-pereulok'] = 'yes';
            }

            $containerConfig->setLabels(new \ArrayObject($labels));
            $hostConfig->setBinds($binds);
            try {
                $container = $containerManager->create($containerConfig);
                try {
                    $this->dockerNetwork->attachToNetwork($container->getId());
                } catch (HttpException $e) {
                    $this->delete($container->getId());
                    throw $e;
                }

            } catch (HttpException $e) {
                LfsConfigParser::cleanFiles("{$this->cfgBasePath}/$configDir");
                throw $e;
            }

            return $container->getId();
        } catch (HttpException $e) {
            throw new LsnDockerException($e->getMessage(), $e);
        }
    }

    /**
     * @return \Docker\API\Model\ContainerInfo[]|\Psr\Http\Message\ResponseInterface
     */
    public function findAllLfsContainers($state = null)
    {
        $state = (array)$state;
        $containerInfos = $this->docker->getContainerManager()->findAll([
            'filters' => json_encode([
                'label' => ['lfs-server'],
                'status' => $state ?: ['created', 'restarting', 'running', 'paused', 'exited', 'dead'],
            ])
        ]);
        return $containerInfos;
    }

    /**
     * @param $container Container
     * @return string
     */
    private function getLfsConfigPath(Container $container)
    {
        return $this->cfgBasePath . "/" . $container->getConfig()->getLabels()['conf-dir'];
    }

    /**
     * @param $config
     * @param $originalConfig
     */
    private function getParamThatRequiresContainerRecreation($config, $originalConfig)
    {
        $paramsRequireContainerRecreation = ['image', 'pereulok', 'port'];

        foreach ($paramsRequireContainerRecreation as $param) {
            if (isset($config[$param]) && !(isset($originalConfig[$param]) || $config[$param] != $originalConfig[$param])) {
                return $param;
            }
        }
        return false;
    }
    
    public function stopAllTestContainers()
    {
        $containerInfos = $this->docker->getContainerManager()->findAll([
            'filters' => json_encode([
                'label' => ['lfs-server', 'is-testing'],
                'status' => ['created', 'restarting', 'running', 'paused', 'exited', 'dead'],
            ])
        ]);

        foreach ($containerInfos as $containerInfo) {
            if ($containerInfo->getState() == 'running') {
                $this->stop($containerInfo->getId());
            }
        }
    }

    public function deleteAllTestContainers()
    {
        $containerInfos = $this->docker->getContainerManager()->findAll([
            'filters' => json_encode([
                'label' => ['lfs-server', 'is-testing'],
            ])
        ]);

        foreach ($containerInfos as $containerInfo) {
            if ($containerInfo->getState() == 'running') {
                $this->stop($containerInfo->getId());
            }
            $this->delete($containerInfo->getId());
        }
    }

}

