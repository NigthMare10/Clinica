<?php

namespace App\Enums;

enum FiscalAuthorizationStatus: string
{
    case ACTIVE = 'ACTIVE';
    case EXHAUSTED = 'EXHAUSTED';
    case EXPIRED = 'EXPIRED';
    case DISABLED = 'DISABLED';
}
