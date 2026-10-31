<?php

namespace App\Enums;

enum AfterSalesStatus: string
{
    case New = 'NEW';
    case Approved = 'APPROVED';
    case Received = 'RECEIVED';
    case Processing = 'PROCESSING';
    case AwaitingRefund = 'AWAITING_REFUND';
    case ReadyForPickup = 'READY_FOR_PICKUP';
    case Completed = 'COMPLETED';
    case Rejected = 'REJECTED';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Mới',
            self::Approved => 'Đã duyệt',
            self::Received => 'Đã tiếp nhận',
            self::Processing => 'Đang xử lý',
            self::AwaitingRefund => 'Chờ hoàn tiền',
            self::ReadyForPickup => 'Sẵn sàng bàn giao',
            self::Completed => 'Hoàn tất',
            self::Rejected => 'Đã từ chối',
            self::Cancelled => 'Đã hủy',
        };
    }
}
