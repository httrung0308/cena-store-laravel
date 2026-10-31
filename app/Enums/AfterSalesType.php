<?php

namespace App\Enums;

enum AfterSalesType: string
{
    case SizeExchange = 'SIZE_EXCHANGE';
    case WarrantyRepair = 'WARRANTY_REPAIR';
    case DefectReturn = 'DEFECT_RETURN';

    public function label(): string
    {
        return match ($this) {
            self::SizeExchange => 'Đổi kích cỡ',
            self::WarrantyRepair => 'Sửa chữa bảo hành',
            self::DefectReturn => 'Trả hàng lỗi',
        };
    }
}
