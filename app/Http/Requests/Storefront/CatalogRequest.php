<?php

namespace App\Http\Requests\Storefront;

use App\Enums\ClassificationGroup;
use App\Exceptions\DomainException;
use App\Support\Money;
use App\Support\ShoeSize;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query-string filters for /shoes and the style_color selector of /shoes/{slug}. Everything is whitelisted;
 * sizes keep their canonical string form (40.5000 → "40.5") and prices are integer VND.
 */
class CatalogRequest extends FormRequest
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
        $amount = static function (string $attribute, mixed $value, Closure $fail): void {
            try {
                Money::integer($value);
            } catch (DomainException) {
                $fail('Giá phải là số nguyên VND không âm trong giới hạn.');
            }
        };

        return [
            'search' => ['nullable', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:200', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D'],
            'gender' => ['nullable', Rule::in(['men', 'women', 'kids', 'unisex'])],
            'silhouette' => ['nullable', 'array', 'max:20'],
            'silhouette.*' => [
                'string',
                'distinct',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',
                Rule::exists('classifications', 'slug')
                    ->where('classification_group', ClassificationGroup::Silhouette->value)
                    ->where('is_active', true),
            ],
            'activity' => ['nullable', 'array', 'max:20'],
            'activity.*' => [
                'string',
                'distinct',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',
                Rule::exists('classifications', 'slug')
                    ->where('classification_group', ClassificationGroup::Activity->value)
                    ->where('is_active', true),
            ],
            'colors' => ['nullable', 'array', 'max:12'],
            'colors.*' => ['string', 'distinct', Rule::in(array_keys(config('store.palette')))],
            'size' => ['nullable', 'string', static function (string $attribute, mixed $value, Closure $fail): void {
                try {
                    ShoeSize::normalize($value);
                } catch (DomainException) {
                    $fail('Size phải là số nguyên hoặc nửa size hợp lệ.');
                }
            }],
            'min_price' => ['nullable', 'integer', 'min:0', $amount],
            'max_price' => ['nullable', 'integer', 'min:0', $amount, ...($this->filled('min_price') ? ['gte:min_price'] : [])],
            'promotion' => ['nullable', 'boolean'],
            'in_stock' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['newest', 'price_asc', 'price_desc', 'name_asc', 'name_desc'])],
            'page' => ['nullable', 'integer', 'between:1,2147483647'],
            'style_color' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array{search?: string, category?: string, gender?: string, silhouette?: list<string>, activity?: list<string>, colors?: list<string>, size?: string, min_price?: int, max_price?: int, promotion: bool, in_stock: bool, sort: string, page?: int, style_color?: string}
     */
    public function filters(): array
    {
        $data = array_filter($this->validated(), fn (mixed $value): bool => $value !== null && $value !== '');
        if (isset($data['size'])) {
            $data['size'] = ShoeSize::normalize($data['size']);
        }
        foreach (['min_price', 'max_price'] as $key) {
            if (isset($data[$key])) {
                $data[$key] = Money::integer($data[$key]);
            }
        }
        foreach (['promotion', 'in_stock'] as $key) {
            $data[$key] = (bool) ($data[$key] ?? false);
        }
        if (isset($data['page'])) {
            $data['page'] = (int) $data['page'];
        }
        $data['sort'] ??= 'newest';

        return $data;
    }
}
