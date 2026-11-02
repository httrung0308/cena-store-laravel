<?php

namespace Database\Seeders;

use App\Enums\GenderGroup;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ProductManagementTaxonomyService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class ProductDemoTaxonomySeeder extends Seeder
{
    public function run(ProductManagementTaxonomyService $service): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Product demo seeds require local/testing.');
        }
        DB::transaction(function () use ($service): void {
            $actor = User::where('is_active', true)->whereHas('roles', fn ($q) => $q->where('slug', 'admin'))->orderBy('id')->first();
            if (! $actor) {
                throw new LogicException('Seed an explicitly enabled demo administrator first.');
            }
            foreach (GenderGroup::cases() as $gender) {
                $slug = strtolower($gender->value);
                if (! Category::withTrashed()->where('slug', $slug)->exists()) {
                    $service->saveCategory($actor, null, ['name' => $gender->label(), 'slug' => $slug, 'gender_group' => $gender->value, 'parent_id' => null, 'display_order' => 1, 'is_active' => 1]);
                }
            }
            foreach (['DEMO-NCC-1', 'DEMO-NCC-2', 'DEMO-NCC-3'] as $code) {
                if (! Supplier::withTrashed()->where('code', $code)->exists()) {
                    $service->saveSupplier($actor, null, ['code' => $code, 'name' => 'Nhà cung cấp mẫu '.$code, 'phone' => '0900000000', 'is_active' => 1]);
                }
            }
        }, 3);
    }
}
