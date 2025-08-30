<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Artist = 'artist';
    case User = 'user';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Artist => 'Artist',
            self::User => 'User',
        };
    }
}
