<?php
/**
 * Created by PhpStorm.
 * User: ele
 * Date: 11/16/16
 * Time: 11:48 AM
 */

namespace Lsn\Service\Aux;

use Docker\Docker;
use Lsn\Helper\DockerImageManager;

/**
 * This class runs docker X11 Server for LFS servers.
 *
 * Class XServerService
 * @package Lsn
 */
class XServerContainerService extends AbstractSingletonContainer
{
    const CONTAINER_NAME = 'xserver';
    const VNC_PASSWORD = 'docker';

    /**
     * XServerService constructor.
     * @param $docker
     */
    public function __construct(Docker $docker, DockerImageManager $builderContainer)
    {
        if (!$builderContainer->hasImage(self::CONTAINER_NAME)) {
            $builderContainer->buildStaticImage(self::CONTAINER_NAME);
        }
        parent::__construct($docker, 'xserver', self::CONTAINER_NAME, ["VNC_PASSWORD=".self::VNC_PASSWORD], 5900);
    }
}