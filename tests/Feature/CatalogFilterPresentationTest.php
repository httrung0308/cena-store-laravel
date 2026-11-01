<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class CatalogFilterPresentationTest extends TestCase
{
    public function test_size_decimal_and_multicolor_filters_are_strings_and_safe(): void
    {
        $filters = ['size' => '40.5', 'colors' => ['black', 'red'], 'search' => '"><script>alert(1)</script>'];
        $colorOptions = [['value' => 'red', 'label' => 'Đỏ'], ['value' => 'black', 'label' => 'Đen']];
        $html = Blade::render('<x-catalog.filter-panel action="/shoes" :filters="$filters" :color-options="$colorOptions" />', compact('filters', 'colorOptions'));
        $this->assertStringContainsString('value="40.5"', $html);
        $this->assertStringContainsString('value="red"', $html);
        $this->assertStringContainsString('value="black"', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('oldest', $html);
    }
}
