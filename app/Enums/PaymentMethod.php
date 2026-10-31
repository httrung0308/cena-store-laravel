<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cod = 'COD';
    case Vietqr = 'VIETQR';
    case Vnpay = 'VNPAY';

    public function label(): string
    {
        return match ($this) {
            self::Cod => 'Thanh toán khi nhận hàng',
            self::Vietqr => 'Chuyển khoản VietQR',
            self::Vnpay => 'Thanh toán VNPay',
        };
    }
}
