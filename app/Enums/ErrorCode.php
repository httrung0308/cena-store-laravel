<?php

namespace App\Enums;

enum ErrorCode: string
{
    case InvalidAmount = 'INVALID_AMOUNT';
    case AmountOverflow = 'AMOUNT_OVERFLOW';
    case InvalidCost = 'INVALID_COST';
    case InvalidQuantity = 'INVALID_QUANTITY';
    case InvalidAllocation = 'INVALID_ALLOCATION';
    case InvalidSize = 'INVALID_SIZE';
    case InvalidDateRange = 'INVALID_DATE_RANGE';
    case InvalidPayload = 'INVALID_PAYLOAD';
    case ValidationFailed = 'VALIDATION_FAILED';
    case BadRequest = 'BAD_REQUEST';
    case Unauthenticated = 'UNAUTHENTICATED';
    case Forbidden = 'FORBIDDEN';
    case NotFound = 'NOT_FOUND';
    case MethodNotAllowed = 'METHOD_NOT_ALLOWED';
    case Conflict = 'CONFLICT';
    case SessionExpired = 'SESSION_EXPIRED';
    case TooManyRequests = 'TOO_MANY_REQUESTS';
    case InternalError = 'INTERNAL_ERROR';
    case ServiceUnavailable = 'SERVICE_UNAVAILABLE';
    case EmptyCart = 'EMPTY_CART';
    case CartStale = 'CART_STALE';
    case ProductUnavailable = 'PRODUCT_UNAVAILABLE';
    case StockUnavailable = 'STOCK_UNAVAILABLE';
    case PriceChanged = 'PRICE_CHANGED';
    case CouponExhausted = 'COUPON_EXHAUSTED';
    case InvalidAddress = 'INVALID_ADDRESS';
    case PaymentMethodDisabled = 'PAYMENT_METHOD_DISABLED';
    case IdempotencyMismatch = 'IDEMPOTENCY_MISMATCH';
    case StateConflict = 'STATE_CONFLICT';

    public function httpStatus(): int
    {
        return match ($this) {
            self::InvalidAmount => 422,
            self::AmountOverflow => 422,
            self::InvalidCost => 422,
            self::InvalidQuantity => 422,
            self::InvalidAllocation => 422,
            self::InvalidSize => 422,
            self::InvalidDateRange => 422,
            self::InvalidPayload => 422,
            self::ValidationFailed => 422,
            self::BadRequest => 400,
            self::Unauthenticated => 401,
            self::Forbidden => 403,
            self::NotFound => 404,
            self::MethodNotAllowed => 405,
            self::Conflict => 409,
            self::SessionExpired => 419,
            self::TooManyRequests => 429,
            self::InternalError => 500,
            self::ServiceUnavailable => 503,
            self::EmptyCart => 422,
            self::CartStale => 409,
            self::ProductUnavailable => 422,
            self::StockUnavailable => 409,
            self::PriceChanged => 409,
            self::CouponExhausted => 409,
            self::InvalidAddress => 422,
            self::PaymentMethodDisabled => 422,
            self::IdempotencyMismatch => 409,
            self::StateConflict => 409,
        };
    }

    public function message(): string
    {
        return match ($this) {
            self::InvalidAmount => 'Số tiền không hợp lệ.',
            self::AmountOverflow => 'Số tiền vượt giới hạn cho phép.',
            self::InvalidCost => 'Giá vốn không hợp lệ.',
            self::InvalidQuantity => 'Số lượng không hợp lệ.',
            self::InvalidAllocation => 'Dữ liệu phân bổ không hợp lệ.',
            self::InvalidSize => 'Kích cỡ không hợp lệ.',
            self::InvalidDateRange => 'Khoảng thời gian không hợp lệ.',
            self::InvalidPayload => 'Dữ liệu yêu cầu không hợp lệ.',
            self::ValidationFailed => 'Vui lòng kiểm tra lại dữ liệu.',
            self::BadRequest => 'Yêu cầu không hợp lệ.',
            self::Unauthenticated => 'Vui lòng đăng nhập để tiếp tục.',
            self::Forbidden => 'Bạn không có quyền thực hiện thao tác này.',
            self::NotFound => 'Không tìm thấy dữ liệu.',
            self::MethodNotAllowed => 'Phương thức yêu cầu không được hỗ trợ.',
            self::Conflict => 'Dữ liệu đã thay đổi. Vui lòng tải lại.',
            self::SessionExpired => 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang.',
            self::TooManyRequests => 'Bạn thao tác quá nhanh. Vui lòng thử lại sau.',
            self::InternalError => 'Đã xảy ra lỗi. Vui lòng cung cấp mã tra cứu khi liên hệ hỗ trợ.',
            self::ServiceUnavailable => 'Dịch vụ tạm thời chưa sẵn sàng.',
            self::EmptyCart => 'Giỏ hàng đang trống.',
            self::CartStale => 'Giỏ hàng đã thay đổi. Vui lòng kiểm tra lại.',
            self::ProductUnavailable => 'Sản phẩm hiện không thể mua.',
            self::StockUnavailable => 'Số lượng hàng khả dụng đã thay đổi.',
            self::PriceChanged => 'Giá hoặc phí vận chuyển đã thay đổi. Vui lòng xác nhận lại.',
            self::CouponExhausted => 'Mã ưu đãi đã hết lượt sử dụng.',
            self::InvalidAddress => 'Địa chỉ giao hàng không hợp lệ.',
            self::PaymentMethodDisabled => 'Phương thức thanh toán hiện chưa khả dụng.',
            self::IdempotencyMismatch => 'Mã yêu cầu đã được dùng với nội dung khác.',
            self::StateConflict => 'Trạng thái hiện tại không cho phép thao tác này.',
        };
    }

    public function label(): string
    {
        return $this->message();
    }
}
