<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class CatalogFilterPresentationTest extends TestCase
{
    public function test_size_decimal_and_search_filters_are_strings_and_safe_without_color_filter(): void
    {
        $filters = ['size' => '40.5', 'colors' => ['black', 'red'], 'search' => '"><script>alert(1)</script>'];
        $html = Blade::render('<x-catalog.filter-panel action="/shoes" :filters="$filters" />', compact('filters'));
        $this->assertStringContainsString('value="40.5"', $html);
        $this->assertStringNotContainsString('name="colors[]"', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('oldest', $html);
    }
}
