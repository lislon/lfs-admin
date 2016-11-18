<?php
/**
 * Created by PhpStorm.
 * User: ele
 * Date: 11/18/16
 * Time: 10:29 PM
 */

namespace Lsn\Helper;


use Docker\Docker;

class DockerUtils
{
    private static function tempdir($dir = false, $prefix = 'php')
    {
        $tempfile = tempnam(sys_get_temp_dir(), '');
        if (file_exists($tempfile)) {
            unlink($tempfile);
        }
        mkdir($tempfile);
        if (is_dir($tempfile)) {
            return $tempfile;
        }
    }


    public static function readContainerFile(Docker $docker, $containerId, $filename)
    {
        $response = $docker->getContainerManager()->getArchive($containerId, ['path' => $filename]);

        $dir = self::tempdir();

        try {
            file_put_contents("$dir/archive.tar", $response->getBody()->getContents());

            $pharData = new \PharData("$dir/archive.tar");
            $pharData->decompressFiles();
            if ($pharData->count() != 1) {
                throw new \Exception("Number of files should be!");
            }
            foreach ($pharData as $file) {
                return file_get_contents($file->getPathname());
            }

        } finally {
            foreach (glob($dir . '/*') as $file) {
                if (is_file($file))
                    unlink($file);
            }
            rmdir($dir);
        }
    }
}