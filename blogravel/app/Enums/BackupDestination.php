<?php

namespace App\Enums;

enum BackupDestination: string
{
    case Email = 'email';
    case Ftp = 'ftp';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::Ftp => 'FTP/SFTP',
            self::Both => 'Email & FTP/SFTP',
        };
    }

    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
