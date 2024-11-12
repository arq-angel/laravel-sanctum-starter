<?php

namespace App\Traits\Api\V1\CRUDTraits;

trait RequestTraits
{
    /**
     * @return int
     */
    public function paginationLimit(): int
    {
        return 25;
    }
}
