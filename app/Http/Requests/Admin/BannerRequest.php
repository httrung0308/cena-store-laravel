<?php

namespace App\Http\Requests\Admin;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class BannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $isCreate = $this->isMethod('POST');

        return [
            'title' => ['required', 'string', 'max:150'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'image' => [
                $isCreate ? 'required' : 'nullable',
                'image',
                'mimes:jpeg,png,webp',
                'max:2048',
            ],
            'mobile_image' => [
                'nullable',
                'image',
                'mimes:jpeg,png,webp',
                'max:2048',
            ],
            'link_url' => [
                'nullable',
                'string',
                'max:500',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || trim($value) === '') {
                        return;
                    }
                    $url = trim($value);
                    if (str_starts_with($url, '/')) {
                        if (str_starts_with($url, '//')) {
                            $fail('Đường dẫn không hợp lệ.');
                        }

                        return;
                    }
                    if (filter_var($url, FILTER_VALIDATE_URL)) {
                        $scheme = parse_url($url, PHP_URL_SCHEME);
                        if (in_array(strtolower((string) $scheme), ['http', 'https'], true)) {
                            return;
                        }
                    }
                    $fail('Đường dẫn liên kết phải là đường dẫn nội bộ (bắt đầu bằng /) hoặc URL http://, https://.');
                },
            ],
            'cta_text' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['required', 'integer', 'between:0,65535'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'title.required' => 'Vui lòng nhập tiêu đề banner.',
            'title.max' => 'Tiêu đề không được vượt quá 150 ký tự.',
            'subtitle.max' => 'Phụ đề không được vượt quá 255 ký tự.',
            'image.required' => 'Vui lòng chọn ảnh banner phiên bản Desktop.',
            'image.image' => 'File tải lên phải là hình ảnh hợp lệ.',
            'image.mimes' => 'Ảnh chỉ chấp nhận định dạng JPG, PNG hoặc WebP.',
            'image.max' => 'Dung lượng ảnh không được vượt quá 2MB.',
            'mobile_image.image' => 'File ảnh mobile phải là hình ảnh hợp lệ.',
            'mobile_image.mimes' => 'Ảnh mobile chỉ chấp nhận định dạng JPG, PNG hoặc WebP.',
            'mobile_image.max' => 'Dung lượng ảnh mobile không được vượt quá 2MB.',
            'cta_text.max' => 'Nút CTA không được vượt quá 50 ký tự.',
            'sort_order.required' => 'Vui lòng nhập thứ tự hiển thị.',
            'sort_order.between' => 'Thứ tự hiển thị phải từ 0 đến 65535.',
            'is_active.required' => 'Vui lòng chọn trạng thái kích hoạt.',
        ];
    }
}
