<?php

namespace App\Http\Requests\Account;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A review of one delivered pair: 0.5–5 stars in half steps and a 10–2000 character comment.
 */
class ReviewRequest extends FormRequest
{
    /** @var list<string> */
    public const RATINGS = ['0.5', '1.0', '1.5', '2.0', '2.5', '3.0', '3.5', '4.0', '4.5', '5.0'];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $rating = $this->input('rating');
        if (is_string($rating) && preg_match('/^[0-9](\.[05])?$/D', $rating)) {
            $this->merge(['rating' => number_format((float) $rating, 1, '.', '')]);
        }
        if (is_string($this->input('comment'))) {
            $this->merge(['comment' => trim($this->input('comment'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'unit_id' => ['required', 'integer', 'min:1'],
            'rating' => ['required', 'string', Rule::in(self::RATINGS)],
            'comment' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rating.in' => 'Số sao từ 0,5 đến 5, bước 0,5.',
            'comment.min' => 'Nhận xét cần ít nhất 10 ký tự.',
            'comment.max' => 'Nhận xét tối đa 2000 ký tự.',
        ];
    }
}
