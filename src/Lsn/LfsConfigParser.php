<?php
/**
 * Created by PhpStorm.
 * User: ele
 * Date: 11/15/16
 * Time: 5:06 PM
 */

namespace Lsn;


use Lsn\Exception\LsnException;

/**
 * Generates setup.cfg, welcome.txt, tracks.txt from array of parameters
 *
 * Class LfsFilesGenerator
 * @package Lsn
 */
class LfsConfigManager
{
    const defaultConfig = [
        'log' => 'log.log',
        'lytdir' => 'layout',
        'carsmax' => 38,
        'carsguest' => 1,
        'pps' => 6,
        'qual' => 0,
        'wind' => 0,
        'vote' => 'no',
        'usemaster' => 'yes',
        'select' => 'no',
        'start' => 'finish',
        'autosave' => 0,
    ];

    const validExtraParams = [
        'pereulok',
        'image'
    ];

    const validLfsParams = [
        "host",
        "port",
        "pass",
        "admin",
        "mode",
        "usemaster",
        "track",
        "cars",
        "maxguests",
        "adminslots",
        "carsmax",
        "carsguest",
        "pps",
        "qual",
        "laps",
        "wind",
        "vote",
        "select",
        "autokick",
        "rstmin",
        "rstend",
        "midrace",
        "mustpit",
        "canreset",
        "fcv",
        "cruise",
        "start",
        "player",
        "welcome",
        "autosave",
    ];


    /**
     * Removes lfs configuration files in given path
     *
     * @param $basePath
     */
    public static function cleanFiles($basePath)
    {
        if (file_exists($basePath)) {
            foreach (new \DirectoryIterator($basePath) as $dir) {
                @unlink($dir->getPathname());
            }
            @rmdir($basePath);
        }
    }

    /**
     * Read's server host.txt file.
     *
     * @param $basePath
     * @return array|null Key-value config values, or null when host.txt is not found
     */
    public static function readStat($basePath)
    {
        $stat = [];

        if (!($file = @file_get_contents(glob($basePath."/host*.txt"), "r"))) {
            return null;
        }

        foreach (explode(PHP_EOL, $file) as $line) {
            list($name, $val) = explode("=", $line);
            $stat[$name] = $val;
        }

        return $stat;
    }

    /**
     * Read's server logs.txt file.
     *
     * @param $basePath
     * @return array|null Key-value config values, or null when host.txt is not found
     */
    public static function readLog($basePath)
    {
        if (!($file = @file_get_contents($basePath."/log.txt", "r"))) {
            return null;
        }

        return $file;
    }


    /**
     * Reads LFS configs and return it as array.
     *
     * @param $basePath
     * @return array
     * @throws LsnException when setup.cfg not found
     */
    public static function readConfig($basePath)
    {
        $config = [];

        $setupCfgPath = $basePath . "/setup.cfg";
        $inF = fopen($setupCfgPath, "r");

        if (!$inF) {
            throw new LsnException("Can't open '$setupCfgPath'!");
        }

        try {
            while (($line = fgets($inF)) !== false) {
                if (preg_match("@^/([^=/]+)=(.*?)\s*$@", $line, $match)) {
                    // stip out log parameters
                    if (in_array($match[1], self::validLfsParams)) {
                        $config[$match[1]] = $match[2];
                    }
                }
            }

            if (!empty($config['welcome']) && file_exists($basePath."/welcome.txt")) {
                $config['welcome'] = file_get_contents($basePath."/welcome.txt");
            }
            if (!empty($config['tracks']) && file_exists($basePath."/tracks.txt")) {
                $config['tracks'] = explode(PHP_EOL, file_get_contents($basePath."/tracks.txt"));
            }

        } finally {
            fclose($inF);
        }
        return $config;
    }

    /**
     * Generate lfs configuration files based on $cfg in directory $basePath
     *
     * @param $basePath string Directory name where files are stored
     * @param $cfg array Server configuration associative array
     * @throws LsnException
     */
    public static function writeConfig($basePath, $cfg)
    {
        $lfsConfig = self::defaultConfig;

        foreach ($cfg as $key => $value) {
            if (in_array($key, self::validLfsParams)) {
                $lfsConfig[$key] = $value;
            } else if (!in_array($key, self::validExtraParams)) {
                throw new LsnException("Parameter '$cfg' is not recognized");
            }
        }

        if (!file_exists($basePath)) {
            if (!@mkdir($basePath, 0775, true)) {
                throw new LsnException("Can't create directory '$basePath'! Permission problem?");
            }
        }

        if (@file_put_contents("$basePath/welcome.txt", isset($cfg['welcome']) ? $cfg['welcome'] : '') === false) {
            throw new LsnException("Failed to write at '\"$basePath/welcome.cfg\"'");
        }

        if (@file_put_contents("$basePath/tracks.txt", isset($cfg['tracks']) ? implode(PHP_EOL, (array)$cfg['tracks']) : '') === false) {
            throw new LsnException("Failed to write at '\"$basePath/tracks.cfg\"'");
        }
        // empty files for mapping
        @touch("$basePath/log.log");
        @touch("$basePath/host.txt");

        if (!empty($cfg['welcome'])) {
            $lfsConfig['welcome'] = 'welcome.txt';
        }

        if (!empty($cfg['tracks'])) {
            $lfsConfig['tracks'] = 'tracks.txt';
        }

        $fileContents = [];
        $fileContents[] = '// This file is automatically generated at '.date('Y-m-d H:i:s').'';

        foreach ($lfsConfig as $key => $value) {
            $fileContents[] = "/$key=$value";
        }
        $fileContents[] = "";

        if (!@file_put_contents("$basePath/setup.cfg", implode("\n", $fileContents))) {
            throw new LsnException("Failed to write at '\"$basePath/setup.cfg\"'");
        }
    }
}