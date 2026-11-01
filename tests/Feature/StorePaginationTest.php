<?php

namespace Tests\Feature;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class StorePaginationTest extends TestCase
{
    public function test_links_preserve_filters_and_escape_query(): void
    {
        $paginator = new LengthAwarePaginator([], 30, 9, 2, ['path' => '/shoes']);
        $paginator->appends(['search' => '<script>bad</script>', 'gender' => 'MEN', 'colors' => ['black', 'red'], 'sort' => 'price_asc']);
        $html = Blade::render('<x-ui.pagination :paginator="$paginator" />', compact('paginator'));
        $this->assertStringContainsString('search=%3Cscript%3Ebad%3C%2Fscript%3E', $html);
        $this->assertStringContainsString('gender=MEN', $html);
        $this->assertStringContainsString('colors%5B0%5D=black', $html);
        $this->assertStringContainsString('aria-current="page"', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_empty_and_single_page_have_no_pagination(): void
    {
        foreach ([0, 1, 9] as $total) {
            $paginator = new LengthAwarePaginator([], $total, 9, 1, ['path' => '/shoes']);
            $this->assertStringNotContainsString('<nav', Blade::render('<x-ui.pagination :paginator="$paginator" />', compact('paginator')));
        }
    }
}
