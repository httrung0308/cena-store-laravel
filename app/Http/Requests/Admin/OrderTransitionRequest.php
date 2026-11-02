<?php

namespace App\Http\Requests\Admin;

use App\Enums\OrderStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One back-office order transition. The route names the action; fields are only required by the action that uses them.
 */
class OrderTransitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $action = (string) $this->route('action');

        return [
            'expected_status' => ['required', Rule::enum(OrderStatus::class)],
            'carrier_name' => [Rule::requiredIf($action === 'ship'), 'nullable', 'string', 'max:100'],
            'tracking_code' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._\/-]+$/D'],
            'receipt_reference' => [Rule::requiredIf($action === 'deliver'), 'nullable', 'string', 'max:150', 'regex:/^[A-Za-z0-9._\/-]+$/D'],
            'reason' => [Rule::requiredIf(in_array($action, ['cancel', 'fail'], true)), 'nullable', 'string', 'min:5', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['carrier_name' => 'đơn vị vận chuyển', 'tracking_code' => 'mã vận đơn', 'receipt_reference' => 'mã phiếu thu COD', 'reason' => 'lý do'];
    }
}
