<?php

namespace App\Enums;

enum SystemRole: string
{
    case PlatformAdmin = 'platform_admin';
    case WorkspaceUser = 'workspace_user';
}
