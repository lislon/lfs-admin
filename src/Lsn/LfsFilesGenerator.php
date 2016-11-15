<?php
/**
 * Created by PhpStorm.
 * User: ele
 * Date: 11/15/16
 * Time: 5:06 PM
 */

namespace Lsn;


class LfsFilesGenerator
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

    const allowParams = [
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


    public static function cleanFiles($basePath)
    {
        @unlink($basePath.'/welcome.txt');
        @unlink($basePath.'/tracks.txt');
        @unlink($basePath.'/setup.cfg');
        @unlink($basePath);
    }

    public static function generateFiles($basePath, $cfg)
    {
        $lfsConfig = array_merge(
            self::defaultConfig,
            array_filter($cfg,
                function($key)
                {
                    return in_array($key, self::allowParams);
                }, ARRAY_FILTER_USE_KEY)
        );


        if (!file_exists($basePath)) {
            if (!@mkdir($basePath, 0775, true)) {
                throw new LsnException("Can't create directory '$basePath'! Permission problem?");
            }
        }

        if (@file_put_contents("$basePath/welcome.txt", isset($cfg['welcome']) ? $cfg['welcome'] : '') === false) {
            throw new LsnException("Failed to write at '\"$basePath/welcome.cfg\"'");
        }

        if (@file_put_contents("$basePath/tracks.txt", isset($cfg['tracks']) ? $cfg['tracks'] : '') === false) {
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