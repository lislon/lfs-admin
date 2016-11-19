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


            // Stop container if necessary
            if ($this->getStateFromContainer($container) != 'stopped') {
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
            $containerInfos = $this->findAllLfsContainers();

            return array_map(function (ContainerInfo $container) {
                return [
                    'id' => $container->getId(),
                    'state' => $container->getState(),
                ];
            }, $containerInfos);
        } catch (HttpException $e) {
            throw new LsnDockerException($e->getMessage(), $e);
        }
    }

    private function getStateFromContainer(Container $container) {
        $state = $container->getState();
        if ($state->getRunning()) {
            return 'running';
        }
        if ($state->getRestarting()) {
            return 'restarting';
        }
        return 'stopped';
    }

    public function get($containerId)
    {
        try {
            $container = $this->docker->getContainerManager()->find($containerId);
            $result = [
                'id'    => $container->getId(),
                'state' => $this->getStateFromContainer($container),
                'pereulok' => !empty($container->getConfig()->getLabels()['lfs-pereulok']),
                'image' => $container->getConfig()->getLabels()['lfs-image'],
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


                    $containerInfos = $this->findAllLfsContainers('running');
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
            throw new LsnException($e->getMessage(), $e);
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

            if (($param = $this->getParamThatRequriresContainerRecreation($config, $originalConfig)) !== false) {
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

            $lfsImage = $config['image'];
            $port = $config['port'];
            $configDir = $port . '-' . time();
            $this->validateImage($lfsImage);


            //        if ($this->isContainerExists($this->getContainerName($serverName))) {
            //            $this->delete($serverName);
            //        }

            $containerManager = $this->docker->getContainerManager();

            $containerConfig = new ContainerConfig();
            $containerConfig->setImage('monowine');
            $containerConfig->setWorkingDir("/lfs");
            $labels = [
                'lfs-server' => 'true',
                'conf-dir' => $configDir,
                'lfs-image' => $lfsImage,
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

            $binds = [
                "{$this->lfsBasePath}/$lfsImage:/lfs",
                "{$this->cfgBasePath}/$configDir/setup.cfg:/lfs/setup.cfg",
                "{$this->cfgBasePath}/$configDir/welcome.txt:/lfs/welcome.txt",
                "{$this->cfgBasePath}/$configDir/tracks.txt:/lfs/tracks.txt",
                "/etc/localtime:/etc/localtime"
//                "{$this->cfgBasePath}/$configDir/host.txt:/lfs/host{$port}.txt",
//                "{$this->cfgBasePath}/$configDir/log.log:/lfs/log.log",
            ];

            if (!empty($config['pereulok'])) {
                $binds[] = "{$this->lfsBasePath}/lfspLauncher.exe:/lfs/LFSP.exe";
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
     * @param $lfsImage
     * @throws LsnException
     */
    private function validateImage($lfsImage)
    {
        if (!preg_match("/^[\\w-]*\\.[\\w-]*$/", $lfsImage)) {
            throw new LsnException("Lfs image have wrong characters [A-Z0-9.] is allowed");
        }
        $filename = $this->lfsBasePath."/".$lfsImage;
        if (!file_exists($filename)) {
            throw new LsnException("Lfs image '$lfsImage' is not found ($filename not exists)");
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
    private function getParamThatRequriresContainerRecreation($config, $originalConfig)
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

}

