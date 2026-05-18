<?php

namespace App\Support;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;

class WindowsCompatibleFilesystem extends Filesystem
{
    /**
     * Write the contents of a file, replacing it atomically if it already exists.
     *
     * On Windows, the final rename can fail transiently if the target is still
     * being released by another process, so we retry and fall back to a copy.
     */
    public function replace($path, $content, $mode = null)
    {
        clearstatcache(true, $path);

        $path = realpath($path) ?: $path;

        $tempPath = tempnam(dirname($path), basename($path));

        if ($tempPath === false) {
            throw new RuntimeException(sprintf('Unable to create a temporary file for [%s].', $path));
        }

        if (! is_null($mode)) {
            chmod($tempPath, $mode);
        } else {
            chmod($tempPath, 0777 - umask());
        }

        file_put_contents($tempPath, $content);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            if (@rename($tempPath, $path)) {
                return;
            }

            clearstatcache(true, $path);
            usleep(50000 * ($attempt + 1));
        }

        if ($this->exists($path)) {
            @unlink($path);
        }

        if (@rename($tempPath, $path)) {
            return;
        }

        if (! @copy($tempPath, $path)) {
            @unlink($tempPath);

            throw new RuntimeException(sprintf('Unable to replace file at [%s].', $path));
        }

        if (! is_null($mode)) {
            chmod($path, $mode);
        } else {
            chmod($path, 0777 - umask());
        }

        @unlink($tempPath);
    }
}