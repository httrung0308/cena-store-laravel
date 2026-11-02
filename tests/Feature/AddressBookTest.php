<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\AdministrativeUnit;
use App\Models\User;
use App\Services\AddressService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AddressBookTest extends TestCase
{
    use DatabaseTransactions;

    private User $owner;

    private AdministrativeUnit $province;

    private AdministrativeUnit $ward;

    private AdministrativeUnit $otherWard;

    protected function setUp(): void
    {
        parent::setUp();
        config(['store.shipping.dataset_version' => 'addr-test-v1']);
        // SYNTHETIC TEST ONLY: one current snapshot with leading-zero codes, plus a foreign ward.
        $this->province = AdministrativeUnit::factory()->create(['code' => '01', 'name' => 'Tỉnh Thử', 'dataset_version' => 'addr-test-v1']);
        $this->ward = AdministrativeUnit::factory()->wardOf($this->province)->create(['code' => '00004', 'name' => 'Phường Thử']);
        $other = AdministrativeUnit::factory()->create(['code' => '79', 'name' => 'Tỉnh Khác', 'dataset_version' => 'addr-test-v1']);
        $this->otherWard = AdministrativeUnit::factory()->wardOf($other)->create(['code' => '26734', 'name' => 'Phường Khác']);
        $this->owner = User::factory()->customer()->create();
    }

    /** @return array<string, mixed> */
    private function form(array $overrides = []): array
    {
        return array_merge([
            'recipient_name' => ' Người   Nhận ',
            'phone' => '+84 901 234 567',
            'province_code' => '01',
            'ward_code' => '00004',
            'street_address' => '12 Nguyễn Văn Bảo',
        ], $overrides);
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get(route('account.addresses.index'))->assertRedirect(route('login'));
        $this->post(route('account.addresses.store'), $this->form())->assertRedirect(route('login'));
    }

    public function test_first_address_becomes_default_with_location_snapshot(): void
    {
        $this->actingAs($this->owner)->post(route('account.addresses.store'), $this->form())
            ->assertRedirect(route('account.addresses.index'));

        $address = Address::query()->where('user_id', $this->owner->id)->sole();
        $this->assertTrue($address->is_default);
        $this->assertSame('Người Nhận', $address->recipient_name);
        $this->assertSame('0901234567', $address->phone);
        $this->assertSame($this->ward->id, $address->ward_id);
        $this->assertSame('01', $address->province_code_snapshot);
        $this->assertSame('00004', $address->ward_code_snapshot);
        $this->assertSame('Phường Thử', $address->ward_name_snapshot);
        $this->assertSame('addr-test-v1', $address->dataset_version);
    }

    public function test_ward_must_belong_to_the_province_of_the_current_snapshot(): void
    {
        $old = AdministrativeUnit::factory()->create(['code' => '02', 'dataset_version' => 'addr-test-v0']);
        AdministrativeUnit::factory()->wardOf($old)->create(['code' => '00010']);

        $this->actingAs($this->owner)->from(route('account.addresses.create'))
            ->post(route('account.addresses.store'), $this->form(['ward_code' => $this->otherWard->code]))
            ->assertSessionHasErrors(['ward_code' => 'Phường / xã không thuộc tỉnh / thành phố đã chọn.']);
        $this->post(route('account.addresses.store'), $this->form(['province_code' => '02', 'ward_code' => '00010']))
            ->assertSessionHasErrors('province_code');
        $this->post(route('account.addresses.store'), $this->form(['province_code' => 1, 'ward_code' => '4', 'phone' => '123']))
            ->assertSessionHasErrors(['province_code', 'ward_code', 'phone']);

        $this->assertSame(0, Address::query()->where('user_id', $this->owner->id)->count());
    }

    public function test_choosing_a_new_default_leaves_exactly_one(): void
    {
        $service = app(AddressService::class);
        $first = $service->create($this->owner, ['recipient_name' => 'A', 'phone' => '0901234567', 'province_code' => '01', 'ward_code' => '00004', 'street_address' => '1 A', 'is_default' => false]);

        $this->actingAs($this->owner)->post(route('account.addresses.store'), $this->form(['is_default' => ['1']]));
        $second = Address::query()->where('user_id', $this->owner->id)->latest('id')->first();
        $this->assertTrue($second->is_default);
        $this->assertFalse($first->fresh()->is_default);

        $this->patch(route('account.addresses.default', $first->id))->assertRedirect(route('account.addresses.index'));
        $this->assertSame([$first->id], Address::query()->where('user_id', $this->owner->id)->where('is_default', true)->pluck('id')->all());
    }

    public function test_update_retakes_snapshot_and_cannot_untick_the_only_default(): void
    {
        $address = app(AddressService::class)->create($this->owner, ['recipient_name' => 'A', 'phone' => '0901234567', 'province_code' => '01', 'ward_code' => '00004', 'street_address' => '1 A', 'is_default' => true]);

        $this->actingAs($this->owner)->put(route('account.addresses.update', $address->id), $this->form([
            'province_code' => '79', 'ward_code' => '26734', 'street_address' => '99 Đường Mới',
        ]))->assertRedirect(route('account.addresses.index'));

        $address->refresh();
        $this->assertSame('79', $address->province_code_snapshot);
        $this->assertSame('Phường Khác', $address->ward_name_snapshot);
        $this->assertSame('99 Đường Mới', $address->street_address);
        $this->assertTrue($address->is_default);
    }

    public function test_deleting_the_default_promotes_the_smallest_remaining_id(): void
    {
        $service = app(AddressService::class);
        $data = ['recipient_name' => 'A', 'phone' => '0901234567', 'province_code' => '01', 'ward_code' => '00004', 'street_address' => '1 A', 'is_default' => false];
        $a = $service->create($this->owner, $data);
        $b = $service->create($this->owner, $data);
        $c = $service->create($this->owner, [...$data, 'is_default' => true]);

        $this->actingAs($this->owner)->delete(route('account.addresses.destroy', $c->id))->assertRedirect(route('account.addresses.index'));

        $this->assertSoftDeleted('addresses', ['id' => $c->id, 'is_default' => false]);
        $this->assertTrue($a->fresh()->is_default);
        $this->assertFalse($b->fresh()->is_default);

        $this->delete(route('account.addresses.destroy', $a->id));
        $this->delete(route('account.addresses.destroy', $b->id));
        $this->assertSame(0, Address::query()->where('user_id', $this->owner->id)->count());
        $this->assertSame(3, Address::withTrashed()->where('user_id', $this->owner->id)->count());
    }

    public function test_another_users_address_is_not_found_for_every_action(): void
    {
        $theirs = app(AddressService::class)->create(User::factory()->customer()->create(), ['recipient_name' => 'B', 'phone' => '0901234567', 'province_code' => '01', 'ward_code' => '00004', 'street_address' => '2 B', 'is_default' => true]);
        $this->actingAs($this->owner);

        $this->get(route('account.addresses.edit', $theirs->id))->assertNotFound();
        $this->put(route('account.addresses.update', $theirs->id), $this->form())->assertNotFound();
        $this->patch(route('account.addresses.default', $theirs->id))->assertNotFound();
        $this->delete(route('account.addresses.destroy', $theirs->id))->assertNotFound();
        $this->get(route('account.addresses.index'))->assertDontSee('2 B');

        $this->assertFalse($theirs->fresh()->trashed());
        $this->assertSame('2 B', $theirs->fresh()->street_address);
    }

    public function test_database_rejects_a_second_live_default_for_one_user(): void
    {
        $data = ['recipient_name' => 'A', 'phone' => '0901234567', 'province_code' => '01', 'ward_code' => '00004', 'street_address' => '1 A', 'is_default' => true];
        app(AddressService::class)->create($this->owner, $data);
        $second = app(AddressService::class)->create($this->owner, [...$data, 'is_default' => false]);

        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('addresses')->where('id', $second->id)->update(['is_default' => true]);
    }

    public function test_pages_render_escaped_data_picker_url_and_outdated_notice(): void
    {
        $address = app(AddressService::class)->create($this->owner, ['recipient_name' => '<script>x</script>', 'phone' => '0901234567', 'province_code' => '01', 'ward_code' => '00004', 'street_address' => '1 A', 'is_default' => true]);
        DB::table('addresses')->where('id', $address->id)->update(['dataset_version' => 'addr-test-v0']);

        $this->actingAs($this->owner)->get(route('account.addresses.index'))
            ->assertOk()
            ->assertSee('&lt;script&gt;x&lt;/script&gt;', false)
            ->assertDontSee('<script>x</script>', false)
            ->assertSee('Mặc định')
            ->assertSee('danh mục địa giới cũ')
            ->assertSee('href="'.route('account.addresses.index').'"', false);

        $this->get(route('account.addresses.create'))
            ->assertOk()
            ->assertSee('Tỉnh Thử')
            ->assertSee('api\/locations\/provinces\/__CODE__\/wards', false)
            ->assertSee('name="is_default[]"', false);

        $this->get(route('account.addresses.edit', $address->id))
            ->assertOk()
            ->assertSee("initialWardCode: '00004'", false)
            ->assertDontSee('name="is_default[]"', false);
    }
}
