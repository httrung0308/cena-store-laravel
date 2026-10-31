<?php

namespace App\Enums;

enum OutboxFailureReason: string
{
    case HandlerMissing = 'HANDLER_MISSING';
    case DeliveryFailed = 'DELIVERY_FAILED';
    case AttemptsExhausted = 'ATTEMPTS_EXHAUSTED';
    case InvalidPayload = 'INVALID_PAYLOAD';

    public function label(): string
    {
        return match ($this) {
            self::HandlerMissing => 'Chưa có bộ giao thông báo phù hợp',
            self::DeliveryFailed => 'Giao thông báo thất bại',
            self::AttemptsExhausted => 'Đã hết số lần thử',
            self::InvalidPayload => 'Dữ liệu thông báo không hợp lệ',
        };
    }
}
