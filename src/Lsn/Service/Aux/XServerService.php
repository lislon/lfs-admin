<?php
/**
 * Created by PhpStorm.
 * User: ele
 * Date: 11/16/16
 * Time: 11:48 AM
 */

namespace Lsn\Service\Aux;

use Docker\Docker;

/**
 * This class runs docker X11 Server for LFS servers.
 *
 * Class XServerService
 * @package Lsn
 */
class XServerService extends AbstractSingletonContainer
{
    const CONTAINER_NAME = 'xserver';
    const VNC_PASSWORD = 'docker';

    /**
     * XServerService constructor.
     * @param $docker
     */
    public function __construct(Docker $docker)
    {
        parent::__construct($docker, 'xserver', self::CONTAINER_NAME, ["VNC_PASSWORD=".self::VNC_PASSWORD], 5900);
    }
}