<?php

namespace Tests\Feature;

use App\Enums\RoleSlug;
use App\Exceptions\DomainException;
use App\Models\User;
use App\Models\UserRole;
use App\Services\UserAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use DatabaseTransactions;

    private const PASSWORD = 'Mat-khau-cu-8';

    public function test_only_admins_reach_user_management(): void
    {
        $this->get(route('admin.users.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->customer()->create())->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs(User::factory()->staff()->create())->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs(User::factory()->staff()->create())->get(route('admin.dashboard'))->assertOk()->assertSee('(chỉ Admin)')->assertDontSee('href="'.route('admin.users.index').'"', false);

        $admin = User::factory()->administrator()->create();
        $target = User::factory()->customer()->create(['full_name' => 'Khách <i>Tìm</i>', 'email' => 'tim-kiem@nike.test']);
        $this->actingAs($admin)->get(route('admin.users.index', ['search' => 'tim-kiem', 'role' => 'customer']))
            ->assertOk()->assertSee('tim-kiem@nike.test')->assertSee('Khách &lt;i&gt;Tìm&lt;/i&gt;', false);
        $this->get(route('admin.users.index', ['search' => 'tim-kiem', 'role' => 'staff']))->assertDontSee('tim-kiem@nike.test');
        $this->get(route('admin.users.show', $target))->assertOk()->assertSee('Khóa tài khoản');
    }

    public function test_locking_signs_the_user_out_and_unlocking_lets_them_back(): void
    {
        $admin = User::factory()->administrator()->create();
        $target = User::factory()->customer()->create();
        $version = $target->auth_version;
        $this->actingAs($target)->get(route('account.index'))->assertOk();

        $this->actingAs($admin)->patch(route('admin.users.active', $target), ['active' => 0])->assertRedirect(route('admin.users.show', $target));
        $target->refresh();
        $this->assertFalse($target->is_active);
        $this->assertSame($version + 1, $target->auth_version);

        $this->app['auth']->forgetGuards();
        $this->actingAs($target)->get(route('account.index'))->assertRedirect(route('login'));

        $this->app['auth']->forgetGuards();
        $this->actingAs($admin)->patch(route('admin.users.active', $target), ['active' => 1]);
        $this->assertTrue($target->fresh()->is_active);
    }

    public function test_admin_cannot_lock_or_demote_themselves_and_the_last_admin_is_protected(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin)->from(route('admin.users.show', $admin))
            ->patch(route('admin.users.active', $admin), ['active' => 0])->assertSessionHasErrors('user');
        $this->put(route('admin.users.roles', $admin), ['roles' => ['staff']])->assertSessionHasErrors('roles');
        $this->assertTrue($admin->fresh()->is_active);
        $this->assertTrue($admin->fresh()->hasRole(RoleSlug::Admin));

        // With every other Admin inactive, nobody may remove the last active one.
        UserRole::query()->whereHas('role', fn ($q) => $q->where('slug', 'admin'))->where('user_id', '!=', $admin->id)
            ->each(fn (UserRole $assignment) => $assignment->user()->first()?->forceFill(['is_active' => false])->save());
        $service = app(UserAccessService::class);
        $staff = User::factory()->staff()->create();
        try {
            $service->setActive($staff, $admin, false);
            $this->fail('The last active Admin was locked.');
        } catch (DomainException) {
            $this->assertTrue($admin->fresh()->is_active);
        }
    }

    public function test_roles_are_replaced_as_a_set_and_need_at_least_one(): void
    {
        $admin = User::factory()->administrator()->create();
        User::factory()->staff()->create();
        $target = User::factory()->customer()->create();
        $this->actingAs($admin)->put(route('admin.users.roles', $target), ['roles' => ['staff', 'customer']])->assertRedirect();
        $target->refresh();
        $this->assertTrue($target->hasRole(RoleSlug::Staff) && $target->hasRole(RoleSlug::Customer));
        $this->assertSame($admin->id, UserRole::query()->where('user_id', $target->id)->whereHas('role', fn ($q) => $q->where('slug', 'staff'))->value('assigned_by'));

        $this->from(route('admin.users.show', $target))->put(route('admin.users.roles', $target), ['roles' => []])->assertSessionHasErrors('roles');
        $this->put(route('admin.users.roles', $target), ['roles' => ['owner']])->assertSessionHasErrors('roles.0');
        $this->assertSame(2, $target->roles()->count());
    }

    public function test_profile_edit_never_touches_email_roles_or_active_flag(): void
    {
        $user = User::factory()->customer()->create(['email' => 'giu-nguyen@nike.test']);
        $this->actingAs($user)->patch(route('account.profile.update'), [
            'full_name' => '  Tên   Mới  ', 'phone' => '+84 912 345 678', 'email' => 'doi@nike.test', 'is_active' => 0, 'roles' => ['admin'],
        ])->assertRedirect(route('account.settings'));

        $user->refresh();
        $this->assertSame(['Tên Mới', '0912345678', 'giu-nguyen@nike.test', true], [$user->full_name, $user->phone, $user->email, $user->is_active]);
        $this->assertFalse($user->hasRole(RoleSlug::Admin));
    }

    public function test_password_change_requires_the_current_one_and_ends_every_session(): void
    {
        RateLimiter::clear('login:doi-mat-khau@nike.test|127.0.0.1');
        $user = User::factory()->customer()->create(['email' => 'doi-mat-khau@nike.test', 'password' => self::PASSWORD]);
        $this->actingAs($user)->from(route('account.settings'))
            ->patch(route('account.password.update'), ['current_password' => 'sai-mat-khau', 'password' => 'Mat-khau-moi-9', 'password_confirmation' => 'Mat-khau-moi-9'])
            ->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check(self::PASSWORD, $user->fresh()->password));

        $this->patch(route('account.password.update'), ['current_password' => self::PASSWORD, 'password' => 'Mat-khau-moi-9', 'password_confirmation' => 'Mat-khau-moi-9'])
            ->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertTrue(Hash::check('Mat-khau-moi-9', $user->fresh()->password));
        $this->assertSame($user->auth_version + 1, $user->fresh()->auth_version);

        $this->post(route('login.store'), ['email' => 'doi-mat-khau@nike.test', 'password' => 'Mat-khau-moi-9'])->assertRedirect(route('home'));
    }
}
