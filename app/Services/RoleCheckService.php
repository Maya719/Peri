<?php

namespace App\Services;

use App\Facades\Helper;
use App\Models\User;

class RoleCheckService
{
    public function isAllowed(User $user): bool
    {
        return $user->hasRole('Admin') || Helper::isAssignUsers();
    }
}