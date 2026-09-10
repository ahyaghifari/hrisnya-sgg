<?php

namespace App\Enum;

enum OrgStructurLevel: string
{
    case LEVEL_DEPARTMENT = 'DEPARTMENT';
    case LEVEL_DIVISION = 'DIVISION';
    case LEVEL_SUBDIVISION = 'SUB_DIVISION';
}
