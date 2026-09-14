<?php

namespace App\Enums;

enum DeletionReason: string
{
    case SelfClosed = 'self_closed';
    case AdminRemoved = 'admin_removed';
    case TenantClosed = 'tenant_closed';
}
