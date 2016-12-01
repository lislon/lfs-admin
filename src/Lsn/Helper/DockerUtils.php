<?php
/**
 * Created by PhpStorm.
 * User: ele
 * Date: 11/18/16
 * Time: 10:29 PM
 */

namespace Lsn\Helper;


use Docker\Docker;
use GuzzleHttp\Psr7\LazyOpenStream;
use Lsn\Helper\TempDir;

class DockerUtils
{
    public static function readContainerFile(Docker $docker, $containerId, $filename)
    {
        $response = $docker->getContainerManager()->getArchive($containerId, ['path' => $filename]);

        $tempfile = new TempFile(null, ".tar");
        $writeStream = new LazyOpenStream($tempfile->getPath(), 'w');
        \GuzzleHttp\Psr7\copy_to_stream($response->getBody(), $writeStream);

        $pharData = new \PharData($tempfile->getPath());
        $pharData->decompressFiles();
        if ($pharData->count() != 1) {
            throw new \Exception("Number of files should be!");
        }
        foreach ($pharData as $file) {
            // TODO: Return log file as stream to prevent memory
            $fileContents = file_get_contents($file->getPathname());
            unlink($file->getPathname());
            return $fileContents;
        }
    }
}