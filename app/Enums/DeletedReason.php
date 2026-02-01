<?php

namespace App\Enums;

enum DeletedReason: string
{
    case Reprocess = 'reprocess';
    case UserDelete = 'user_delete';
    case AccountDelete = 'account_delete';
}
