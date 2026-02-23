<?php

namespace App\Enums;

enum WorkspaceRole: string
{
    case OwnerAdmin = 'owner_admin';
    case Trainer = 'trainer';
}
