<?php
/**
 * Created by PhpStorm.
 * User: ele
 * Date: 1/4/17
 * Time: 3:20 PM
 */

namespace Lsn\Service\Aux;


use Docker\Docker;

class MysqlContainerService extends AbstractSingletonContainer
{
    const CONTAINER_NAME = 'mysql';
    const ROOT_PASSWORD = 'toor';

    /**
     * XServerService constructor.
     * @param $docker
     */
    public function __construct(Docker $docker)
    {
        parent::__construct($docker, 'mysql:5', self::CONTAINER_NAME, ["MYSQL_ROOT_PASSWORD=".self::ROOT_PASSWORD], 3306);
    }

}