<?php

namespace Tests\Unit;

use App\Support\ArticleFormatter;
use PHPUnit\Framework\TestCase;

class ArticleFormatterTest extends TestCase
{
    public function test_editor_markup_becomes_headings_lists_quotes_and_inline_styles(): void
    {
        $text = "## Chọn size\n"
            ."Đo chân vào **buổi chiều**, lúc chân *nở nhất*.\n"
            ."Cộng thêm 0,5 cm.\n\n"
            ."### Lưu ý\n"
            ."- Mang tất khi đo\n"
            ."* Chân bè chọn tăng nửa size\n\n"
            ."1. Đo chiều dài\n"
            ."2) Đối chiếu bảng size\n\n"
            ."> Đổi size miễn phí trong 7 ngày\n"
            .'> nếu giày còn tem mác';

        $this->assertSame(
            '<h2>Chọn size</h2>'
            .'<p>Đo chân vào <strong>buổi chiều</strong>, lúc chân <em>nở nhất</em>.<br>Cộng thêm 0,5 cm.</p>'
            .'<h3>Lưu ý</h3>'
            .'<ul><li>Mang tất khi đo</li><li>Chân bè chọn tăng nửa size</li></ul>'
            .'<ol><li>Đo chiều dài</li><li>Đối chiếu bảng size</li></ol>'
            .'<blockquote><p>Đổi size miễn phí trong 7 ngày<br>nếu giày còn tem mác</p></blockquote>',
            ArticleFormatter::toHtml($text)->toHtml(),
        );
    }

    public function test_plain_articles_written_before_the_editor_keep_their_paragraphs(): void
    {
        $this->assertSame(
            '<p>Đoạn một.</p><p>Đoạn hai,<br>xuống dòng.</p>',
            ArticleFormatter::toHtml("Đoạn một.\n\nĐoạn hai,\r\nxuống dòng.\n\n\n")->toHtml(),
        );
        $this->assertSame('', ArticleFormatter::toHtml("  \n ")->toHtml());
    }

    public function test_everything_is_escaped_before_markup_so_no_other_tag_can_appear(): void
    {
        $html = ArticleFormatter::toHtml("## <script>alert(1)</script>\n\n**<img src=x onerror=alert(1)>** & \"quotes\"\n\n- <b>x</b>")->toHtml();

        $this->assertSame(
            '<h2>&lt;script&gt;alert(1)&lt;/script&gt;</h2>'
            .'<p><strong>&lt;img src=x onerror=alert(1)&gt;</strong> &amp; &quot;quotes&quot;</p>'
            .'<ul><li>&lt;b&gt;x&lt;/b&gt;</li></ul>',
            $html,
        );
        preg_match_all('/<\/?([a-z0-9]+)/', $html, $tags);
        $this->assertSame([], array_values(array_diff(array_unique($tags[1]), ['h2', 'h3', 'p', 'br', 'strong', 'em', 'ul', 'ol', 'li', 'blockquote'])));
    }

    public function test_vietnamese_letters_containing_byte_0x85_are_never_split(): void
    {
        // "ễ" is E1 BB 85 in UTF-8; 0x85 alone is the NEL line break.
        $this->assertSame('<p>Miễn phí, tiễn khách, diễn đàn</p>', ArticleFormatter::toHtml('Miễn phí, tiễn khách, diễn đàn')->toHtml());
    }

    public function test_stray_asterisks_and_hashes_stay_as_text(): void
    {
        $this->assertSame(
            '<p>Giá 2*3 = 6, sao * lẻ, #1 bán chạy và # không phải tiêu đề, ** trống **</p>',
            ArticleFormatter::toHtml('Giá 2*3 = 6, sao * lẻ, #1 bán chạy và # không phải tiêu đề, ** trống **')->toHtml(),
        );
    }
}
