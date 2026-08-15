<?php

namespace Modules\Pos\app\Policies;

class PosPolicy
{
    public function access($user): bool
    {
        return $user !== null;
    }
}
