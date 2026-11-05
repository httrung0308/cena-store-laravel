<?php

namespace App\Http\Requests\Admin;

use App\Enums\ArticleStatus;
use App\Models\Article;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Plain-text article fields; HTML tags are rejected and the cover is checked by its real content type. */
class ArticleRequest extends FormRequest
{
    private const NO_HTML = 'not_regex:/<\s*[\/!?a-zA-Z]/';

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200', self::NO_HTML],
            'summary' => ['required', 'string', 'max:500', self::NO_HTML],
            'body' => ['required', 'string', 'max:20000', self::NO_HTML],
            'status' => ['required', Rule::in($this->allowedStatuses())],
            'cover' => [$this->route('article') === null ? 'required' : 'nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120'],
        ];
    }

    /**
     * Draft or published; an article that is already hidden may also stay hidden while it is edited.
     *
     * @return list<string>
     */
    private function allowedStatuses(): array
    {
        $statuses = [ArticleStatus::Draft->value, ArticleStatus::Published->value];
        $article = $this->route('article');
        if ($article instanceof Article && $article->status === ArticleStatus::Hidden) {
            $statuses[] = ArticleStatus::Hidden->value;
        }

        return $statuses;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'title.not_regex' => 'Tiêu đề chỉ được là chữ thường, không chứa thẻ HTML.',
            'summary.not_regex' => 'Tóm tắt chỉ được là chữ thường, không chứa thẻ HTML.',
            'body.not_regex' => 'Nội dung chỉ được là chữ thường, không chứa thẻ HTML.',
            'cover.mimetypes' => 'Ảnh bìa phải là ảnh JPEG, PNG hoặc WebP.',
            'cover.max' => 'Ảnh bìa tối đa 5 MB. Hãy chọn ảnh nhỏ hơn hoặc nén ảnh lại.',
            // PHP refused the file before validation (usually larger than the server's upload limit).
            'cover.uploaded' => 'Không tải được ảnh bìa. Ảnh có thể quá lớn so với giới hạn của máy chủ; hãy chọn ảnh dưới 5 MB rồi thử lại.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['title' => 'tiêu đề', 'summary' => 'tóm tắt', 'body' => 'nội dung', 'status' => 'trạng thái', 'cover' => 'ảnh bìa'];
    }
}
