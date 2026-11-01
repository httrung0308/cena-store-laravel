<?php

namespace Tests\Feature;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use InvalidArgumentException;
use Tests\TestCase;
use Throwable;

class FormTextFieldTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        View::share('errors', new ViewErrorBag);
        request()->setLaravelSession(session()->driver());
    }

    public function test_renders_real_properties_and_accessible_label_and_hint(): void
    {
        $html = $this->renderField(<<<'BLADE'
<x-forms.text-field name="full_name" id="customer-name" label="Họ và tên" value="Nguyễn Văn A" type="text" hint="Nhập tên người nhận" :required="true" autocomplete="name" placeholder="Họ tên" data-field="customer" aria-describedby="external-help" />
BLADE);

        $xpath = $this->xpath($html);
        $label = $this->element($xpath, '//label');
        $input = $this->element($xpath, '//input');
        $hint = $this->element($xpath, '//p[@id="customer-name-hint"]');

        $this->assertSame('Họ và tên', $label->textContent);
        $this->assertSame('customer-name', $label->getAttribute('for'));
        $this->assertSame('customer-name', $input->getAttribute('id'));
        $this->assertSame('full_name', $input->getAttribute('name'));
        $this->assertSame('text', $input->getAttribute('type'));
        $this->assertSame('Nguyễn Văn A', $input->getAttribute('value'));
        $this->assertSame('Họ tên', $input->getAttribute('placeholder'));
        $this->assertSame('name', $input->getAttribute('autocomplete'));
        $this->assertSame('customer', $input->getAttribute('data-field'));
        $this->assertTrue($input->hasAttribute('required'));
        $this->assertSame('true', $input->getAttribute('aria-required'));
        $this->assertSame('false', $input->getAttribute('aria-invalid'));
        $this->assertSame('external-help customer-name-hint', $input->getAttribute('aria-describedby'));
        $this->assertSame('Nhập tên người nhận', $hint->textContent);
        $this->assertSame(0, $xpath->query('//*[@role="alert"]')->length);
    }

    public function test_retains_zero_and_prefers_old_input_over_a_supplied_value(): void
    {
        $zeroInput = $this->element($this->xpath($this->renderField(
            '<x-forms.text-field name="quantity" label="Số lượng" :value="0" type="number" />'
        )), '//input');
        $this->assertSame('0', $zeroInput->getAttribute('value'));
        $this->assertSame('quantity', $zeroInput->getAttribute('id'));

        session()->flashInput(['quantity' => '03']);
        $oldInput = $this->element($this->xpath($this->renderField(
            '<x-forms.text-field name="quantity" label="Số lượng" value="99" type="number" />'
        )), '//input');
        $this->assertSame('03', $oldInput->getAttribute('value'));
    }

    public function test_displays_laravel_error_bag_with_correct_aria_and_escaped_error(): void
    {
        $error = '<img src=x onerror=alert(1)> Lỗi nhập liệu';
        $bag = new ViewErrorBag;
        $bag->put('default', new MessageBag(['email' => [$error, 'Lỗi thứ hai']]));
        View::share('errors', $bag);

        $html = $this->renderField('<x-forms.text-field name="email" label="Email" type="email" hint="Có thể liên hệ" />');
        $xpath = $this->xpath($html);
        $input = $this->element($xpath, '//input');
        $message = $this->element($xpath, '//p[@role="alert"]');

        $this->assertSame('true', $input->getAttribute('aria-invalid'));
        $this->assertSame('email-hint email-error', $input->getAttribute('aria-describedby'));
        $this->assertSame('email-error', $message->getAttribute('id'));
        $this->assertSame($error, $message->textContent);
        $this->assertSame(0, $xpath->query('//img')->length);
        $this->assertStringNotContainsString('Lỗi thứ hai', $html);
        $this->assertStringContainsString('&lt;img', $html);
    }

    public function test_escapes_label_hint_value_and_attribute_injections(): void
    {
        $payload = '"><svg onload=alert(1)></svg>';
        $html = $this->renderField(
            '<x-forms.text-field name="note" :label="$payload" :value="$payload" :hint="$payload" :placeholder="$payload" />',
            ['payload' => $payload]
        );
        $xpath = $this->xpath($html);
        $input = $this->element($xpath, '//input');

        $this->assertSame($payload, $this->element($xpath, '//label')->textContent);
        $this->assertSame($payload, $input->getAttribute('value'));
        $this->assertSame($payload, $input->getAttribute('placeholder'));
        $this->assertSame($payload, $this->element($xpath, '//p[@id="note-hint"]')->textContent);
        $this->assertSame(0, $xpath->query('//svg')->length);
        $this->assertFalse($input->hasAttribute('onload'));
        $this->assertStringNotContainsString('<svg', $html);
        $this->assertStringContainsString('&lt;svg', $html);
    }

    public function test_field_error_standalone_uses_custom_id_and_is_empty_without_error(): void
    {
        $bag = new ViewErrorBag;
        $bag->put('default', new MessageBag(['email' => ['Email không hợp lệ']]));
        View::share('errors', $bag);

        $html = $this->renderField('<x-forms.field-error name="email" id="login-email-error" />');
        $xpath = $this->xpath($html);
        $message = $this->element($xpath, '//p[@role="alert"]');
        $this->assertSame('login-email-error', $message->getAttribute('id'));
        $this->assertSame('Email không hợp lệ', $message->textContent);

        $other = $this->xpath($this->renderField('<x-forms.field-error name="phone" />'));
        $this->assertSame(0, $other->query('//p')->length);
    }

    public function test_does_not_render_password_or_one_time_code_inputs(): void
    {
        foreach ([
            '<x-forms.text-field name="secret" label="Mật khẩu" type="password" />',
            '<x-forms.text-field name="code" label="Mã" autocomplete="one-time-code" />',
        ] as $template) {
            try {
                $this->renderField($template);
                $this->fail('Sensitive input must use its dedicated component.');
            } catch (Throwable $exception) {
                while ($exception->getPrevious() !== null) {
                    $exception = $exception->getPrevious();
                }

                $this->assertInstanceOf(InvalidArgumentException::class, $exception);
                $this->assertStringContainsString('dedicated component', $exception->getMessage());
            }
        }
    }

    public function test_field_does_not_crash_when_old_input_is_an_array(): void
    {
        session()->flashInput(['nickname' => ['unexpected', 'array']]);

        $html = $this->renderField('<x-forms.text-field name="nickname" label="Biệt danh" value="Fallback" />');
        $input = $this->element($this->xpath($html), '//input');
        $this->assertSame('', $input->getAttribute('value'));
    }

    /** @param array<string, mixed> $data */
    private function renderField(string $template, array $data = []): string
    {
        return Blade::render($template, $data);
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            $document->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return new DOMXPath($document);
    }

    private function element(DOMXPath $xpath, string $expression): DOMElement
    {
        $element = $xpath->query($expression)?->item(0);
        $this->assertInstanceOf(DOMElement::class, $element);

        return $element;
    }
}
