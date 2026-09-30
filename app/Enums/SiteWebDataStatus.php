<?php

namespace App\Enums;

enum SiteWebDataStatus: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case Failed = 'failed';
}
