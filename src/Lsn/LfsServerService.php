<?php

namespace Lsn;

use Docker\API\Model\Container;
use Docker\API\Model\ContainerInfo;
use Docker\API\Model\HostConfig;
use Docker\Docker;
use Docker\API\Model\ContainerConfig;
use Http\Client\Common\Exception\ClientErrorException;

class LfsServerService
{
    private $docker;
    private $dockerSettings;
    private $lfsBasePath;
    private $cfgBasePath;

    /**
     * LfsServerService constructor.
     * @param Docker $docker
     * @param $dockerSettings
     *          buildPath: path to lfsdata and lfscfg directories
     */
    public function __construct(Docker $docker, $dockerSettings)
    {
        $this->docker = new Docker();
        $this->dockerSettings = $dockerSettings;
        $this->lfsBasePath = $dockerSettings['buildPath']."/lfsdata";
        $this->cfgBasePath = $dockerSettings['buildPath']."/lfscfg";
    }


    /**
     * Remove LFS server container
     *
     * @param $serverName
     * @throws LsnException
     */
    public function delete($containerId)
    {
        $containerManager = $this->docker->getContainerManager();
        $containerManager->remove($containerId);
    }

    /**
     * Return list of server containers
     */
    public function list()
    {
        $containerInfos = $this->docker->getContainerManager()->findAll([
            'filters' => json_encode([
                'label' => ['lfs-server'],
                'status' => ['created', 'restarting', 'running', 'paused', 'exited', 'dead']
            ])
        ]);

        return array_map(function(ContainerInfo $container) {
            return [
                'id' => $container->getId(),
                'state' => $container->getState(),
                'guests' => null,
                'host' => null,
            ];
        }, $containerInfos);
    }

    public function start($containerId)
    {
        $this->docker->getContainerManager()->start($containerId);

    }

    public function stop($containerId)
    {
        $this->docker->getContainerManager()->stop($containerId);
    }

    public function update($containerId, $config)
    {
        throw new LsnException("Not implemented");
    }

    /**
     * Creates a new docker container for given server.
     *
     * @param $serverName
     * @param $lfsVersion
     * @param $port
     * @param $config
     */
    public function create($config)
    {
        $lfsVersion = $config['version'];
        $port = $config['port'];
        $configDir = $port.'-'.time();
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
            "$port/tcp" => [[ "HostPort" => "$port" ]],
            "$port/udp" => [[ "HostPort" => "$port" ]]
        ]);
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
        } catch (\Exception $e) {
            LfsFilesGenerator::cleanFiles("{$this->cfgBasePath}/$configDir");
            throw $e;
        }

        return $container->getId();
    }

    /**
     * @param $lfsVersion
     * @throws LsnException
     */
    private function validateVersion($lfsVersion)
    {
        if (!preg_match("/^[a-Z0-9.]+$/", $lfsVersion)) {
            throw new LsnException("Lfs version have wrong characters [A-Z0-9.] is allowded");
        }
        $filename = $this->lfsBasePath."/".$lfsVersion;
        if (!file_exists($filename)) {
            throw new LsnException("Lfs version '$lfsVersion' is not found ($filename not exists)");
        }
    }

}