<?php

namespace App\Enum;

enum OrgStructurLevel: int
{
    case LEVEL_DEPARTMENT = 1;
    case LEVEL_DIVISION = 2;
    case LEVEL_SUBDIVISION = 3;
}
