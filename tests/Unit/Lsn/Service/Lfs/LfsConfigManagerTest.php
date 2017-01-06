<?php
/**
 * Created by PhpStorm.
 * User: ele
 * Date: 11/18/16
 * Time: 4:25 PM
 */

namespace Tests\Unit\Lsn\Service\Lfs;



use Lsn\Service\Lfs\LfsConfigParser;

class LfsConfigManagerTest extends \PHPUnit_Framework_TestCase
{
    public function testReadConfig()
    {
        $config = LfsConfigParser::readConfig(__DIR__."/_fixtures");
        $this->assertEquals("baraban2", $config['admin']);
        $this->assertEquals("^7LSN TEST", $config['host']);
        $this->assertEquals(["AU1", "AU1x"], $config['tracks']);
    }

    public function testParseStats()
    {
        $config = LfsConfigParser::parseStats(file_get_contents(__DIR__."/_fixtures/host6051.txt"));
        $this->assertEquals("test", $config['pass']);
    }
}

