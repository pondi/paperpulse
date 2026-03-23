<?php

declare(strict_types=1);

namespace App\Enums;

enum PublicShareAction: string
{
    case View = 'view';
    case DownloadFile = 'download_file';
    case DownloadAll = 'download_all';
    case PasswordAttempt = 'password_attempt';
    case PasswordSuccess = 'password_success';

    public function label(): string
    {
        return match ($this) {
            self::View => 'Viewed Collection',
            self::DownloadFile => 'Downloaded File',
            self::DownloadAll => 'Downloaded All Files',
            self::PasswordAttempt => 'Password Attempt',
            self::PasswordSuccess => 'Password Verified',
        };
    }
}
