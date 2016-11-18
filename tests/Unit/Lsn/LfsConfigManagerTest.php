<?php
/**
 * Created by PhpStorm.
 * User: ele
 * Date: 11/18/16
 * Time: 4:25 PM
 */

namespace Tests\Unit\Lsn;


use Lsn\LfsConfigManager;

class LfsConfigManagerTest extends \PHPUnit_Framework_TestCase
{
    public function testReadFiles()
    {
        $config = LfsConfigManager::readFiles(__DIR__."/_fixtures");
        $this->assertEquals("baraban2", $config['admin']);
        $this->assertEquals(["AU1", "AU1x"], $config['tracks']);
    }
}
