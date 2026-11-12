<?php

namespace Tests\Feature;

use App\Enums\RoleSlug;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SharedFoundationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_navbar_menu_and_collections_link_to_catalogue_by_gender(): void
    {
        $response = $this->get(route('home'))->assertOk();

        foreach (['men', 'women', 'kids', 'unisex'] as $gender) {
            $response->assertSee('href="'.route('shoes.index', ['gender' => $gender]).'"', false);
        }
        $response->assertDontSee('href="#collection-', false);
    }

    public function test_catalogue_route_shows_coming_soon_inside_store_layout_without_intro_animation(): void
    {
        $this->get(route('shoes.index', ['gender' => 'men']))
            ->assertOk()
            ->assertViewIs('catalog.index')
            ->assertSee('Shoes')
            ->assertSee('aria-controls="cart-panel"', false)
            ->assertSee('Khám phá')
            ->assertDontSee('legacy-navigation', false);
    }

    public function test_guest_cannot_open_admin(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
        $this->getJson('/admin')->assertUnauthorized();
    }

    /** @return array<string, array{string}> */
    public static function backOfficeRoles(): array
    {
        return ['admin' => ['administrator'], 'staff' => ['staff']];
    }

    #[DataProvider('backOfficeRoles')]
    public function test_admin_and_staff_open_back_office_with_only_existing_sections_linked(string $state): void
    {
        $user = User::factory()->{$state}()->create(['full_name' => 'Nhân sự <b>Cena</b>']);

        $this->actingAs($user)->get('/admin')
            ->assertOk()
            ->assertViewIs('admin.dashboard')
            ->assertSee('href="'.route('admin.dashboard').'"', false)
            ->assertSee('Nhân sự &lt;b&gt;Cena&lt;/b&gt;', false)
            ->assertSee('href="'.route('admin.products.index').'"', false);
    }

    public function test_customer_inactive_or_deleted_staff_and_user_without_role_are_forbidden(): void
    {
        $this->actingAs(User::factory()->customer()->create())->get('/admin')->assertForbidden();
        $this->actingAs(User::factory()->staff()->inactive()->create())->get('/admin')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();

        $deleted = User::factory()->administrator()->create();
        $deleted->delete();
        $this->actingAs($deleted)->get('/admin')->assertForbidden();
    }

    public function test_revoked_role_is_rechecked_on_the_next_request(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin)->get('/admin')->assertOk();

        UserRole::query()->where('user_id', $admin->id)->delete();

        $this->actingAs($admin->fresh())->get('/admin')->assertForbidden();
    }

    public function test_role_middleware_can_require_a_single_role(): void
    {
        Route::middleware(['web', 'auth', 'role:admin'])->get('/__admin-only', fn (): string => 'ok');

        $this->actingAs(User::factory()->staff()->create())->get('/__admin-only')->assertForbidden();
        $this->actingAs(User::factory()->administrator()->create())->get('/__admin-only')->assertOk()->assertSee('ok');
    }

    public function test_role_factory_states_assign_the_canonical_role(): void
    {
        foreach (['administrator' => RoleSlug::Admin, 'staff' => RoleSlug::Staff, 'customer' => RoleSlug::Customer] as $state => $role) {
            $user = User::factory()->{$state}()->create();

            $this->assertTrue($user->hasRole($role));
            $this->assertSame(1, $user->roles()->count());
        }
    }

    public function test_shared_alpine_components_are_registered_once_in_app_js(): void
    {
        $script = file_get_contents(resource_path('js/app.js'));

        foreach (['addressSelector', 'colorVariants', 'dialogController', 'galleryController', 'passwordField', 'productImages', 'toastsController', 'variantPicker'] as $component) {
            $this->assertSame(1, substr_count($script, "Alpine.data('{$component}'"), $component);
        }
    }

    public function test_store_layout_renders_section_content_and_escapes_title(): void
    {
        $html = Blade::render("@extends('layouts.store')\n@section('title', \$title)\n@section('content')<p>Nội dung</p>@endsection", ['title' => '<script>x</script>']);

        $this->assertStringContainsString('<p>Nội dung</p>', $html);
        $this->assertStringContainsString('&lt;script&gt;x&lt;/script&gt; — Cena', $html);
        $this->assertStringNotContainsString('<script>x</script>', $html);
    }

    public function test_artisan_serve_keeps_the_temp_folder_so_uploads_work_on_windows(): void
    {
        foreach (['TEMP', 'TMP', 'TMPDIR'] as $variable) {
            $this->assertContains($variable, ServeCommand::$passthroughVariables);
        }
        $this->assertContains('PATH', ServeCommand::$passthroughVariables, 'Laravel defaults are kept.');
    }
}
