<?php

namespace App\Enums;

enum AccountRecoveryResult
{
    case RestoredUser;
    case RestoredUserAndTenant;
    case RestoredUserNeedsTenant;
    case AdminRemovalBlocked;
    case TenantClosureBlocked;
    case Expired;
    case ActiveEmailConflict;
    case InvalidCredentials;
}
