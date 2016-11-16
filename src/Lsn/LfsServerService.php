<?php

namespace Lsn;

use Docker\API\Model\Container;
use Docker\API\Model\ContainerInfo;
use Docker\API\Model\HostConfig;
use Docker\Docker;
use Docker\API\Model\ContainerConfig;
use Http\Client\Common\Exception\ClientErrorException;
use Http\Client\Exception\HttpException;

class LfsServerService
{
    private $docker;
    private $dockerSettings;
    private $lfsBasePath;
    private $cfgBasePath;
    private $xServer;

        /**
     * LfsServerService constructor.
     * @param Docker $docker
     * @param $dockerSettings
     * @param XServerService $displayService Service for running x11 server for LFS boxes
     */
    public function __construct(Docker $docker, $dockerSettings, XServerService $displayService)
    {
        $this->docker = $docker;
        $this->dockerSettings = $dockerSettings;
        $this->lfsBasePath = $dockerSettings['buildPath']."/lfsdata";
        $this->cfgBasePath = $dockerSettings['buildPath']."/lfscfg";
        $this->xServer = $displayService;
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
            $containerInfo = $this->docker->getContainerManager()->find($containerId);

            $containerManager = $this->docker->getContainerManager();
            $containerManager->remove($containerId);

            // clean after ourself
            $configDir = $containerInfo->getConfig()->getLabels()['conf-dir'];
            LfsFilesGenerator::cleanFiles("{$this->cfgBasePath}/$configDir");
        } catch (HttpException $e) {
            throw new LsnDockerException($e->getMessage(), $e->getRequest());
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
                    'guests' => null,
                    'host' => null,
                ];
            }, $containerInfos);
        } catch (HttpException $e) {
            throw new LsnDockerException($e->getMessage(), $e->getRequest());
        }
    }

    public function start($containerId)
    {
        try {
            $this->xServer->runIfStopped();
            $this->docker->getContainerManager()->start($containerId);
        } catch (HttpException $e) {

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

    public function update($containerId, $config)
    {
        throw new LsnException("Not implemented");
    }

    /**
     * Creates a new docker container for given server.
     *
     * @param $config
     *  ports - required
     *  version - required
     * @return string id of container
     * @throws LsnException
     */
    public function create($config)
    {
        try {
            $this->xServer->runIfStopped();

            $lfsVersion = $config['version'];
            $port = $config['port'];
            $configDir = $port . '-' . time();
            $this->validateVersion($lfsVersion);


            //        if ($this->isContainerExists($this->getContainerName($serverName))) {
            //            $this->delete($serverName);
            //        }

            $containerManager = $this->docker->getContainerManager();

            $containerConfig = new ContainerConfig();
            $containerConfig->setImage('monowine');
            $containerConfig->setWorkingDir("/lfs");
            $containerConfig->setLabels(new \ArrayObject([
                'lfs-server' => 'true',
                'conf-dir' => $configDir,
                'lfs-version' => $lfsVersion,
            ]));


            $hostConfig = new HostConfig();
            $hostConfig->setPortBindings([
                "$port/tcp" => [["HostPort" => "$port"]],
                "$port/udp" => [["HostPort" => "$port"]]
            ]);
            $hostConfig->setVolumesFrom(["xserver"]);

            $containerConfig->setHostConfig($hostConfig);
            $containerConfig->setExposedPorts([
                "$port/tcp" => new \ArrayObject(),
                "$port/udp" => new \ArrayObject()
            ]);

            LfsFilesGenerator::generateFiles("{$this->cfgBasePath}/$configDir", $config);

            $binds = [
                "{$this->lfsBasePath}/$lfsVersion:/lfs",
                "{$this->cfgBasePath}/$configDir/setup.cfg:/lfs/setup.cfg",
                "{$this->cfgBasePath}/$configDir/welcome.txt:/lfs/welcome.txt",
                "{$this->cfgBasePath}/$configDir/tracks.txt:/lfs/tracks.txt",
                "{$this->cfgBasePath}/$configDir/host.txt:/lfs/host{$port}.txt",
                "{$this->cfgBasePath}/$configDir/log.log:/lfs/log.txt",
            ];

            if (!empty($config['pereulok'])) {
                $binds[] = "{$this->lfsBasePath}/lfspLauncher.exe:/lfs/LFSP.exe";
            }

            $hostConfig->setBinds($binds);
            try {
                $container = $containerManager->create($containerConfig);
            } catch (HttpException $e) {
                LfsFilesGenerator::cleanFiles("{$this->cfgBasePath}/$configDir");
                throw $e;
            }

            return $container->getId();
        } catch (HttpException $e) {
            throw new LsnDockerException($e->getMessage(), $e);
        }
    }

    /**
     * @param $lfsVersion
     * @throws LsnException
     */
    private function validateVersion($lfsVersion)
    {
        if (!preg_match("/^[\\w-]*\\.[\\w-]*$/", $lfsVersion)) {
            throw new LsnException("Lfs version have wrong characters [A-Z0-9.] is allowed");
        }
        $filename = $this->lfsBasePath."/".$lfsVersion;
        if (!file_exists($filename)) {
            throw new LsnException("Lfs version '$lfsVersion' is not found ($filename not exists)");
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

}