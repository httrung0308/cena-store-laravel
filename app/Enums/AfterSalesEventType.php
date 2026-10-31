<?php

namespace App\Enums;

enum AfterSalesEventType: string
{
    case Created = 'CREATED';
    case Transition = 'TRANSITION';
    case Inspected = 'INSPECTED';
    case Handover = 'HANDOVER';
    case Note = 'NOTE';
    case DeadlineExtended = 'DEADLINE_EXTENDED';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Đã tạo',
            self::Transition => 'Chuyển trạng thái',
            self::Inspected => 'Đã kiểm tra',
            self::Handover => 'Bàn giao',
            self::Note => 'Ghi chú',
            self::DeadlineExtended => 'Gia hạn',
        };
    }
}
