<?php

namespace App\Services\Privacy;

use RuntimeException;

class PrivateExportPermissions
{
    public function enforce(string $path, int $mode, string $description): void
    {
        if (! chmod($path, $mode)) {
            throw new RuntimeException("Unable to secure {$description}.");
        }

        clearstatcache(true, $path);
        $effective = fileperms($path);
        if ($effective === false || ($effective & 0777) !== $mode) {
            throw new RuntimeException("Unable to verify {$description} permissions.");
        }
    }
}
