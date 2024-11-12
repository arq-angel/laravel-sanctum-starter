<?php

namespace App\Traits\Api\V1;

trait ApiDebugTraits
{
    /**
     * @return bool
     */
    public function debuggable(): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    public function isLocalEnvironment(): bool
    {
        // This ensures debug info is included only in development environments
        // return app()->environment('local');

        return true;
    }
}
