<?php
/**
 * Created by PhpStorm.
 * User: ele
 * Date: 11/22/16
 * Time: 4:45 PM
 */

namespace Lsn\Helper;


class RecursiveDirCopy
{
    public static function copy($src, $dst)
    {
        $dir = opendir($src);
        @mkdir($dst);
        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . '/' . $file)) {
                    self::copy($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }
}