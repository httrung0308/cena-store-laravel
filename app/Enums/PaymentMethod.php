<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cod = 'COD';
    case PrepaidDemo = 'PREPAID_DEMO';
    case Vietqr = 'VIETQR';
    case Vnpay = 'VNPAY';

    public function label(): string
    {
        return match ($this) {
            self::Cod => 'Thanh toán khi nhận hàng',
            self::PrepaidDemo => 'Thanh toán trước (mô phỏng)',
            self::Vietqr => 'Chuyển khoản VietQR',
            self::Vnpay => 'Thanh toán VNPay',
        };
    }
}
