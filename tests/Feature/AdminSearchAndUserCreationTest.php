<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Enums\RoleSlug;
use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminSearchAndUserCreationTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    private User $staff;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->administrator()->create();
        $this->staff = User::factory()->staff()->create();
        $this->customer = User::factory()->customer()->create();
    }

    public function test_admin_articles_search_filters_by_title_and_combines_with_status_and_sort(): void
    {
        Article::factory()->create([
            'author_id' => $this->admin->id,
            'title' => 'Giày chạy bộ Cena Alpha',
            'status' => ArticleStatus::Published,
            'published_at' => '2026-10-01 08:00:00',
        ]);

        Article::factory()->create([
            'author_id' => $this->admin->id,
            'title' => 'Giày tây Cena Beta',
            'status' => ArticleStatus::Draft,
            'published_at' => null,
        ]);

        Article::factory()->create([
            'author_id' => $this->admin->id,
            'title' => 'Ưu đãi 50% dây giày',
            'status' => ArticleStatus::Published,
            'published_at' => '2026-10-02 08:00:00',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.articles.index', [
                'search' => 'Cena',
                'status' => ArticleStatus::Published->value,
                'sort' => 'helpful',
            ]))
            ->assertOk()
            ->assertSee('Giày chạy bộ Cena Alpha')
            ->assertDontSee('Giày tây Cena Beta')
            ->assertDontSee('Ưu đãi 50% dây giày');

        $this->actingAs($this->admin)
            ->get(route('admin.articles.index', ['search' => '50%']))
            ->assertOk()
            ->assertSee('Ưu đãi 50% dây giày')
            ->assertDontSee('Giày chạy bộ Cena Alpha');

        $this->actingAs($this->admin)
            ->get(route('admin.articles.index', ['search' => 'KhongTonTaiXYZ']))
            ->assertOk()
            ->assertSee('Không tìm thấy bài viết');
    }

    public function test_admin_can_create_staff_and_admin_accounts_without_changing_current_session(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.users.create'))
            ->assertOk()
            ->assertSee('Tạo tài khoản');

        $staffResponse = $this->actingAs($this->admin)
            ->post(route('admin.users.store'), [
                'full_name' => '  Nguyễn Văn   Nhân Viên  ',
                'email' => '  NEW.Staff@Example.com ',
                'phone' => '0912 345 678',
                'password' => 'StrongPass123!',
                'password_confirmation' => 'StrongPass123!',
                'role' => RoleSlug::Staff->value,
            ]);

        $createdStaff = User::where('email', 'new.staff@example.com')->firstOrFail();

        $staffResponse->assertRedirect(route('admin.users.show', $createdStaff));
        $this->assertSame($this->admin->id, auth()->id());
        $this->assertSame('Nguyễn Văn Nhân Viên', $createdStaff->full_name);
        $this->assertSame('0912345678', $createdStaff->phone);
        $this->assertTrue($createdStaff->is_active);
        $this->assertTrue(Hash::check('StrongPass123!', $createdStaff->password));
        $this->assertTrue($createdStaff->hasRole(RoleSlug::Staff));
        $this->assertFalse($createdStaff->hasRole(RoleSlug::Admin));

        $adminResponse = $this->actingAs($this->admin)
            ->post(route('admin.users.store'), [
                'full_name' => 'Trần Quản Trị',
                'email' => 'new.admin@example.com',
                'phone' => '0987654321',
                'password' => 'StrongPass123!',
                'password_confirmation' => 'StrongPass123!',
                'role' => RoleSlug::Admin->value,
            ]);

        $createdAdmin = User::where('email', 'new.admin@example.com')->firstOrFail();

        $adminResponse->assertRedirect(route('admin.users.show', $createdAdmin));
        $this->assertSame($this->admin->id, auth()->id());
        $this->assertTrue($createdAdmin->hasRole(RoleSlug::Admin));
    }

    public function test_staff_and_customer_cannot_access_or_submit_privileged_user_creation(): void
    {
        $payload = [
            'full_name' => 'Blocked User',
            'email' => 'blocked.user@example.com',
            'phone' => '0911222333',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
            'role' => RoleSlug::Staff->value,
        ];

        $this->actingAs($this->staff)
            ->get(route('admin.users.create'))
            ->assertForbidden();

        $this->actingAs($this->staff)
            ->post(route('admin.users.store'), $payload)
            ->assertForbidden();

        $this->actingAs($this->customer)
            ->get(route('admin.users.create'))
            ->assertForbidden();

        $this->actingAs($this->customer)
            ->post(route('admin.users.store'), $payload)
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'blocked.user@example.com']);
    }

    public function test_privileged_user_creation_rejects_duplicate_email_and_non_privileged_roles(): void
    {
        $existing = User::factory()->customer()->create([
            'email' => 'taken.account@example.com',
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.users.store'), [
                'full_name' => 'Duplicate Email',
                'email' => '  TAKEN.Account@example.com ',
                'phone' => '0911222333',
                'password' => 'StrongPass123!',
                'password_confirmation' => 'StrongPass123!',
                'role' => RoleSlug::Staff->value,
            ])
            ->assertSessionHasErrors('email');

        $this->assertSame(1, User::where('email', $existing->email)->count());

        $this->actingAs($this->admin)
            ->post(route('admin.users.store'), [
                'full_name' => 'Invalid Role User',
                'email' => 'invalid.role@example.com',
                'phone' => '0911222444',
                'password' => 'StrongPass123!',
                'password_confirmation' => 'StrongPass123!',
                'role' => RoleSlug::Customer->value,
            ])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'invalid.role@example.com']);
    }
}
