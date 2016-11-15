<?php

namespace Lsn;

use Docker\Docker;
use Docker\API\Model\ContainerConfig;
use Http\Client\Common\Exception\ClientErrorException;

class LfsServerService {

    private $docker;
    private $dockerSettings;

    /**
     * LfsServerService constructor.
     * @param Docker $docker
     * @param $dockerSettings
     *          buildPath: path to lfsdata and lfscfg directories
     */
    public function __construct(Docker $docker, $dockerSettings) {
        $this->docker = new Docker();
        $this->dockerSettings = $dockerSettings;
    }

    public function updateVersion($serverName, $lfsVersion) {

    }

    /**
     * Remove LFS server container
     *
     * @param $serverName
     * @throws LsnException
     */
    public function delete($serverName) {
        $this->validateServerName($serverName);

        $containerManager = $this->docker->getContainerManager();
        $containerManager->remove($this->getContainerName($serverName));
    }

    public function start($serverName) {

    }

    public function status($serverName) {

    }

    public function stop($serverName) {

    }

    private function isContainerExists($containerName) {
        try {
            $this->docker->getContainerManager()->find($containerName);
            return true;
        } catch (ClientErrorException $e) {
            if ($e->getCode() == 404) {
                return false;
            } else {
                throw $e;
            }
        }
    }

    /**
     * Creates a new docker container for given server.
     *
     * @param $serverName
     * @param $lfsVersion
     * @param $port
     * @param $config
     */
    public function create($serverName, $lfsVersion, $port, $config) {

        $this->validateServerName($serverName);
        $this->validateVersion($lfsVersion);


        if ($this->isContainerExists($this->getContainerName($serverName))) {
            $this->delete($serverName);
        }

        $containerManager = $this->docker->getContainerManager();

        $containerConfig = new ContainerConfig();
        $containerConfig->setImage('monowine');
        $containerConfig->setWorkingDir("/lfs");
        $containerConfig->setExposedPorts(["$port" => new \ArrayObject(), "$port/udp" => new \ArrayObject()]);

//        $containerConfig->setVolumes([
//            $this->getLfsDataPath($lfsVersion) => "/lfs",
//            $this->dockerSettings['buildPath'] . "/lfsdata/lfspLauncher.exe" => "/lfs/LFSP.exe",
//            $this->dockerSettings['buildPath'] . "/lfscfg/$serverName/setup.cfg" => "/lfs/setup.cfg",
//            $this->dockerSettings['buildPath'] . "/lfscfg/$serverName/welcome.txt" => "/lfs/welcome.txt",
//            $this->dockerSettings['buildPath'] . "/lfscfg/$serverName/tracks.txt" => "/lfs/tracks.txt",
//        ]);

        $containerCreateResult = $containerManager->create($containerConfig, ["name" => $this->getContainerName($serverName)]);



//        - ./build-data/lfsdata/0.6M:/opt/lfs
//        - ./build-data/lfsdata/lfspLauncher.exe:/opt/lfs/LFSP.exe
//        - ./build-data/lfscfg/6050/setup.cfg:/opt/lfs/setup.cfg
//        - ./build-data/lfscfg/6050/welcome.txt:/opt/lfs/welcome.txt
//        - ./build-data/lfscfg/6050/tracks.txt:/opt/lfs/tracks.txt
//

//        $containerCreateResult = $containerManager->create($containerConfig);

    }

    /**
     * @param $serverName
     * @throws LsnException
     */
    public function validateServerName($serverName)
    {
        if (!preg_match("/^[\w]+$/", $serverName)) {
            throw new LsnException("Server name '$serverName' is invalid'");
        }
    }

    /**
     * @param $lfsVersion
     * @throws LsnException
     */
    public function validateVersion($lfsVersion)
    {
        if (!preg_match("/^[\w.]+$/", $lfsVersion)) {
            throw new LsnException("Lfs version have wrong characters [A-Z0-9.] is allowded");
        }
        $filename = $this->getLfsDataPath($lfsVersion);
        if (!file_exists($filename)) {
            throw new LsnException("Lfs version '$lfsVersion' is not found ($filename not exists)");
        }
    }

    /**
     * @param $lfsVersion
     * @return string
     */
    public function getLfsDataPath($lfsVersion)
    {
        return $this->dockerSettings['buildPath'] . "/lfsdata/" . $lfsVersion;
    }

    /**
     * @param $serverName
     * @return string
     */
    public function getContainerName($serverName)
    {
        return "lfs-server-$serverName";
    }
}