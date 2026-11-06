<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class FormChoiceGroupTest extends TestCase
{
    public function test_leading_zero_and_decimal_values_keep_exact_checked_state_independent_of_order(): void
    {
        $options = [
            ['value' => '40.5', 'label' => 'Size 40.5'],
            ['value' => '00001', 'label' => 'Mã 00001'],
            ['value' => '40', 'label' => 'Size 40'],
        ];
        $render = static fn (array $opts) => Blade::render('<x-forms.choice-group name="sizes" label="Cỡ" mode="checkbox" id="sizes" :options="$options" :value="[\'00001\', \'40.5\']" />', ['options' => $opts]);
        foreach ([$options, array_reverse($options)] as $ordered) {
            $html = $render($ordered);
            $this->assertMatchesRegularExpression('/value="00001"\s+checked/', $html);
            $this->assertMatchesRegularExpression('/value="40\.5"\s+checked/', $html);
            $this->assertStringContainsString('value="40"', $html);
            $this->assertDoesNotMatchRegularExpression('/value="40"\s+checked/', $html);
        }
    }

    public function test_html_escapes_label_and_value_and_generates_distinct_ids(): void
    {
        $options = [['value' => '00001', 'label' => '<script>alert(1)</script>']];
        $html = Blade::render('<x-forms.choice-group name="ward" label="<b>Ward</b>" :options="$options"/><x-forms.choice-group name="ward2" label="Ward 2" :options="$options"/>', compact('options'));
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('&lt;b&gt;Ward&lt;/b&gt;', $html);
        preg_match_all('/id="(choice-[A-Za-z0-9]+)-0"/', $html, $matches);
        $this->assertCount(2, $matches[1]);
        $this->assertNotSame($matches[1][0], $matches[1][1]);
        $this->assertStringContainsString('for="'.$matches[1][0].'-0"', $html);
    }

    public function test_native_checkbox_has_visible_state_marker_and_keeps_accessible_label_binding(): void
    {
        $options = [
            ['value' => '00001', 'label' => 'Phường thử nghiệm'],
            ['value' => '40.5', 'label' => 'Size 40.5', 'disabled' => true],
        ];
        $html = Blade::render('<x-forms.choice-group name="sizes" label="Chọn" id="selection" :options="$options" :value="[\'00001\']"/>', compact('options'));
        $this->assertStringContainsString('for="selection-0"', $html);
        $this->assertMatchesRegularExpression('/id="selection-0"[^>]*value="00001"[^>]*checked[^>]*class="peer sr-only"/s', $html);
        $this->assertStringContainsString('peer-checked:bg-accent', $html);
        $this->assertStringContainsString('peer-checked:text-accent-text', $html);
        $this->assertStringContainsString('peer-focus-visible:ring-4', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertMatchesRegularExpression('/id="selection-1"[^>]*value="40\.5"[^>]*disabled/s', $html);
        // Browser visual/keyboard acceptance is recorded separately; HTML alone cannot prove CSS visibility.
    }

    public function test_radio_preserves_exact_string_choice_and_disabled_state(): void
    {
        $options = [['value' => '00001', 'label' => 'Mã'], ['value' => '1', 'label' => 'Khác']];
        $html = Blade::render('<x-forms.choice-group name="province" label="Tỉnh" mode="radio" :options="$options" :value="\'00001\'" />', compact('options'));
        $this->assertMatchesRegularExpression('/type="radio"[^>]*name="province"[^>]*value="00001"[^>]*checked/s', $html);
        $this->assertDoesNotMatchRegularExpression('/value="1"[^>]*checked/', $html);
        $this->assertStringContainsString('rounded-full', $html);
    }
}
