<?php

namespace App\Enums;

enum AdminRole: string
{
    case Owner = 'owner';
    case Support = 'support';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Владелец',
            self::Support => 'Поддержка',
        };
    }
}
