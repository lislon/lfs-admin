<?php
/**
 * Created by PhpStorm.
 * User: ele
 * Date: 12/1/16
 * Time: 1:32 PM
 */

namespace Lsn;


use Docker\API\Model\Container;
use Docker\API\Model\ContainerInfo;

class DockerStateMapper
{
    public function mapContainerInfoState(ContainerInfo $containerInfo)
    {
        switch ($containerInfo->getState()) {
            case "running";
            case "restarting";
                return $containerInfo->getState();
            default:
                return "stopped";
        }
    }

    public function mapContainerState(Container $container)
    {
        $state = $container->getState();
        if ($state->getRunning()) {
            return 'running';
        }
        if ($state->getRestarting()) {
            return 'restarting';
        }
        return 'stopped';
    }
}