<?php

namespace App\Enums;

enum RoleSlug: string
{
    case Admin = 'admin';
    case Staff = 'staff';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Quản trị viên',
            self::Staff => 'Nhân viên',
            self::Customer => 'Khách hàng',
        };
    }
}
