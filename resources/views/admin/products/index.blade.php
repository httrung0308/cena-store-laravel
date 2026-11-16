@extends('layouts.admin')
@section('title', 'Danh Sách Sản Phẩm')
@section('eyebrow', 'QUẢN TRỊ KHO HÀNG')
@section('actions')
    <div class="flex flex-wrap items-center gap-2">
        <button type="button" x-data x-on:click="$dispatch('toggle-import-panel')"
                class="px-4 py-2 bg-surface hover:bg-border/40 text-muted hover:text-main text-xs font-bold rounded-xl border border-border transition-colors shadow-sm">
            Nhập Excel / CSV
        </button>
        <a class="px-5 py-2.5 bg-accent hover:bg-accent-hover text-accent-text text-xs font-bold rounded-xl transition-colors shadow-md" href="{{ route('admin.products.create') }}">
            + Thêm sản phẩm
        </a>
    </div>
@endsection
@php($images = app(App\Services\CatalogQueryService::class))
@section('content')
    <div x-data="{
        ...adminStatusToggle(),
        ...adminProductFilters({
            baseUrl: @js(route('admin.products.index')),
            initialFilters: @js($filters),
            options: {
                genders: @js($filterGenders),
                silhouettes: @js($filterSilhouettes),
                activities: @js($filterActivities),
            },
        }),
        showImport: @js(session()->has('import_preview') || $errors->has('import_errors')),
        toggleImport() { this.showImport = !this.showImport; }
    }"
    x-on:toggle-import-panel.window="toggleImport()"
    @keydown.escape.window="closeDropdown()"
    class="space-y-6">

        {{-- Excel / CSV Import Section --}}
        <div x-show="showImport" x-transition class="admin-card space-y-4 border-accent/40 bg-surface/80">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-main">Nhập sản phẩm hàng loạt (Excel / CSV)</h2>
                    <p class="text-xs text-muted mt-0.5">Nhập thông tin sản phẩm, danh mục, màu sắc và dải size. Ảnh sản phẩm sẽ được bổ sung sau trên giao diện chỉnh sửa.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('admin.products.template', ['format' => 'xlsx']) }}"
                       class="px-3 py-1.5 bg-surface hover:bg-border/40 text-accent font-bold text-xs rounded-lg border border-border transition-colors">
                        Tải file mẫu (.xlsx)
                    </a>
                    <a href="{{ route('admin.products.template', ['format' => 'csv']) }}"
                       class="px-3 py-1.5 bg-surface hover:bg-border/40 text-muted hover:text-main font-bold text-xs rounded-lg border border-border transition-colors">
                        Tải file mẫu (.csv)
                    </a>
                    <button type="button" x-on:click="showImport = false" class="text-muted hover:text-main text-xs font-semibold px-2 py-1 ml-1" aria-label="Đóng khung nhập file">Đóng</button>
                </div>
            </div>

            @if ($errors->has('import_errors'))
                <div role="alert" class="rounded-xl border border-error/30 bg-error-bg p-4 text-xs font-semibold text-error space-y-2">
                    <p class="font-bold">Chưa thể import. Vui lòng kiểm tra và sửa các dòng sau trong file:</p>
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->get('import_errors') as $errorList)
                            @foreach ((array) $errorList as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Upload Form --}}
            <form method="POST" action="{{ route('admin.products.import.preview') }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3 pt-1">
                @csrf
                <label for="product-spreadsheet-file" class="sr-only">File Excel hoặc CSV</label>
                <input id="product-spreadsheet-file" type="file" name="file" accept=".xlsx,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required
                       class="block w-full max-w-md text-xs text-main file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-surface file:text-main file:border file:border-border hover:file:bg-border/40 cursor-pointer">
                <button type="submit" class="px-5 py-2 bg-accent hover:bg-accent-hover text-accent-text text-xs font-bold rounded-xl transition-colors shadow-md">
                    Kiểm tra file (Xem trước)
                </button>
            </form>

            {{-- Preview section if uploaded successfully --}}
            @if (session()->has('import_preview'))
                @php($previewProducts = session('import_preview'))
                <div class="mt-4 pt-4 border-t border-border/70 space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="text-xs font-bold text-success uppercase">Kết quả kiểm tra file: {{ count($previewProducts) }} sản phẩm hợp lệ</h3>
                            <p class="text-[11px] text-muted">Sản phẩm import chưa có ảnh nên sẽ ở trạng thái <strong class="text-error">Ngừng bán</strong> để đảm bảo không hiển thị thiếu ảnh trên Storefront. Sau khi lưu, bạn bấm "Sửa" để tải ảnh theo màu và mở bán.</p>
                        </div>
                        <form method="POST" action="{{ route('admin.products.import.store') }}">
                            @csrf
                            <input type="hidden" name="products_json" value="{{ json_encode($previewProducts) }}">
                            <button type="submit" class="px-6 py-2 bg-success hover:bg-success/90 text-white text-xs font-bold rounded-xl transition-colors shadow-md">
                                Xác nhận lưu {{ count($previewProducts) }} sản phẩm vào kho
                            </button>
                        </form>
                    </div>

                    <div class="max-h-60 overflow-y-auto border border-border/60 rounded-xl">
                        <table class="w-full text-left text-xs text-muted border-collapse">
                            <thead class="bg-surface text-main font-bold border-b border-border sticky top-0">
                                <tr>
                                    <th class="p-3">Tên sản phẩm</th>
                                    <th class="p-3">Danh mục</th>
                                    <th class="p-3">Giá gốc</th>
                                    <th class="p-3">Các màu</th>
                                    <th class="p-3 text-center">Tổng biến thể</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border bg-surface/40">
                                @foreach ($previewProducts as $p)
                                    <tr>
                                        <td class="p-3 font-bold text-main">{{ $p['name'] }}</td>
                                        <td class="p-3">{{ $p['category_name'] }}</td>
                                        <td class="p-3 font-mono font-bold text-main whitespace-nowrap">{{ number_format($p['base_price'], 0, ',', '.') }} ₫</td>
                                        <td class="p-3">
                                            <div class="flex flex-wrap gap-1.5">
                                                @foreach ($p['colors'] as $col)
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-surface border border-border text-[11px]">
                                                        <span class="w-2.5 h-2.5 rounded-full inline-block border border-border/50" style="background-color: {{ $col['primary_hex'] }}"></span>
                                                        <span>{{ $col['color_name'] }} ({{ count($col['variants']) }} size)</span>
                                                    </span>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td class="p-3 text-center font-bold text-main">{{ $p['total_variants'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        {{-- Search & Multi-Filter Command Bar --}}
        <form method="GET" action="{{ route('admin.products.index') }}" @submit.prevent="applyFilters()" class="admin-card !p-3.5 space-y-2.5">
            {{-- Row 1: Dedicated Full-Width Search Row --}}
            <div class="flex items-center gap-2">
                <div class="relative flex-1">
                    <label for="product-search" class="sr-only">Tìm sản phẩm</label>
                    <svg class="w-4 h-4 text-muted absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 1010.5 18a7.5 7.5 0 006.15-3.35z" />
                    </svg>
                    <input id="product-search" type="search" name="search" x-model="search" value="{{ $filters['search'] }}"
                           placeholder="Tìm kiếm theo tên sản phẩm hoặc mã slug..."
                           class="w-full pl-10 pr-8 py-2 bg-surface border border-border rounded-xl text-xs font-medium text-main placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-accent focus:border-accent">
                    <button type="button" x-show="search !== ''" x-cloak @click="search = ''; applyFilters()"
                            aria-label="Xóa từ khóa tìm kiếm"
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 inline-flex items-center justify-center w-5 h-5 rounded-full text-muted hover:text-main hover:bg-border/60 transition-colors">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <button type="submit" class="px-4 py-2 bg-accent hover:bg-accent-hover text-accent-text text-xs font-bold rounded-xl transition-colors shadow-sm shrink-0">
                    Tìm
                </button>
                <button type="button" @click="mobileFiltersOpen = !mobileFiltersOpen"
                        :aria-expanded="mobileFiltersOpen.toString()"
                        class="md:hidden inline-flex items-center gap-1.5 px-3 py-2 bg-surface hover:bg-border/40 text-main text-xs font-bold rounded-xl border border-border transition-colors shrink-0">
                    <span>Bộ lọc</span>
                </button>
            </div>

            {{-- Filters Region (Desktop always visible; Mobile collapsible via "Bộ lọc" button) --}}
            <div :class="mobileFiltersOpen ? 'block' : 'hidden md:block'"
                 class="space-y-2.5 pt-2 md:pt-0 border-t md:border-t-0 border-border/60">

                {{-- Row 2: Product Classification Filters (Đối tượng, Kiểu dáng, Mục đích) --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                    {{-- 1. Đối tượng (multi-select) --}}
                    <div class="relative min-w-0" @click.outside="if (openDropdown === 'genders') closeDropdown()">
                        <button type="button" @click="toggleDropdown('genders')" :aria-expanded="(openDropdown === 'genders').toString()"
                                :class="genders.length > 0 ? 'border-accent/60 bg-accent/5 text-accent font-bold' : 'border-border bg-surface text-main font-semibold'"
                                class="w-full inline-flex items-center justify-between gap-1.5 px-3 py-2 border rounded-xl text-xs hover:border-accent/60 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                            <span class="truncate">Đối tượng</span>
                            <span class="inline-flex items-center gap-1 shrink-0">
                                <span x-show="genders.length > 0" x-text="genders.length" class="inline-flex items-center justify-center min-w-[1.15rem] h-4 px-1 rounded-full bg-accent text-accent-text text-[10px] font-extrabold"></span>
                                <svg class="w-3.5 h-3.5 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </span>
                        </button>
                        <div x-show="openDropdown === 'genders'" x-cloak class="absolute left-0 right-0 z-30 mt-1.5 w-full max-w-full bg-surface-card border border-border rounded-xl shadow-lg p-2 space-y-1">
                            @foreach ($filterGenders as $gender)
                                <label data-filter-value="{{ $gender['value'] }}"
                                       :class="isSelected('genders', $el.dataset.filterValue) ? 'bg-accent/10 text-accent font-bold border-accent/30' : 'text-main hover:bg-surface border-transparent'"
                                       class="flex items-center gap-2.5 px-2.5 py-1.5 rounded-lg border cursor-pointer text-xs transition-colors select-none focus-within:ring-2 focus-within:ring-accent">
                                    <input type="checkbox" name="genders[]" value="{{ $gender['value'] }}"
                                           :checked="isSelected('genders', $el.value)"
                                           @change="toggleSelection('genders', $el.value)"
                                           class="sr-only">
                                    <span :class="isSelected('genders', $el.closest('label').dataset.filterValue) ? 'bg-accent border-accent text-accent-text' : 'bg-surface-card border-border text-transparent'"
                                          class="w-4 h-4 rounded border flex items-center justify-center shrink-0 transition-colors" aria-hidden="true">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </span>
                                    <span class="flex-1 truncate">{{ $gender['label'] }}</span>
                                </label>
                            @endforeach
                            <div class="flex items-center justify-between pt-1.5 border-t border-border/60 text-[11px]">
                                <button type="button" x-show="genders.length > 0" @click="clearGroup('genders')" class="text-muted hover:text-main font-semibold">Bỏ chọn</button>
                                <button type="button" @click="closeDropdown()" class="ml-auto text-accent font-bold">Đóng</button>
                            </div>
                        </div>
                    </div>

                    {{-- 2. Kiểu dáng (multi-select, searchable) --}}
                    <div class="relative min-w-0" @click.outside="if (openDropdown === 'silhouettes') closeDropdown()">
                        <button type="button" @click="toggleDropdown('silhouettes')" :aria-expanded="(openDropdown === 'silhouettes').toString()"
                                :class="silhouettes.length > 0 ? 'border-accent/60 bg-accent/5 text-accent font-bold' : 'border-border bg-surface text-main font-semibold'"
                                class="w-full inline-flex items-center justify-between gap-1.5 px-3 py-2 border rounded-xl text-xs hover:border-accent/60 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                            <span class="truncate">Kiểu dáng</span>
                            <span class="inline-flex items-center gap-1 shrink-0">
                                <span x-show="silhouettes.length > 0" x-text="silhouettes.length" class="inline-flex items-center justify-center min-w-[1.15rem] h-4 px-1 rounded-full bg-accent text-accent-text text-[10px] font-extrabold"></span>
                                <svg class="w-3.5 h-3.5 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </span>
                        </button>
                        <div x-show="openDropdown === 'silhouettes'" x-cloak class="absolute left-0 right-0 z-30 mt-1.5 w-full max-w-full bg-surface-card border border-border rounded-xl shadow-lg p-2 space-y-2">
                            <input type="search" x-ref="silhouettesSearch" x-model="dropdownSearch.silhouettes" placeholder="Tìm kiểu dáng..."
                                   class="w-full px-2.5 py-1.5 bg-surface border border-border rounded-lg text-xs text-main placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-accent">
                            <div class="max-h-52 overflow-y-auto space-y-1 pr-0.5">
                                @foreach ($filterSilhouettes as $item)
                                    <label data-filter-label="{{ $item['label'] }}"
                                           data-filter-value="{{ $item['id'] }}"
                                           x-show="matchesDropdownSearch('silhouettes', $el.dataset.filterLabel)"
                                           :class="isSelected('silhouettes', $el.dataset.filterValue) ? 'bg-accent/10 text-accent font-bold border-accent/30' : 'text-main hover:bg-surface border-transparent'"
                                           class="flex items-center gap-2.5 px-2.5 py-1.5 rounded-lg border cursor-pointer text-xs transition-colors select-none focus-within:ring-2 focus-within:ring-accent">
                                        <input type="checkbox" name="silhouettes[]" value="{{ $item['id'] }}"
                                               :checked="isSelected('silhouettes', $el.value)"
                                               @change="toggleSelection('silhouettes', $el.value)"
                                               class="sr-only">
                                        <span :class="isSelected('silhouettes', $el.closest('label').dataset.filterValue) ? 'bg-accent border-accent text-accent-text' : 'bg-surface-card border-border text-transparent'"
                                              class="w-4 h-4 rounded border flex items-center justify-center shrink-0 transition-colors" aria-hidden="true">
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </span>
                                        <span class="flex-1 truncate">{{ $item['label'] }}</span>
                                        @if (! $item['is_active'])
                                            <span class="text-[10px] px-1.5 py-0.5 rounded bg-muted/15 text-muted shrink-0">Ẩn</span>
                                        @endif
                                    </label>
                                @endforeach
                            </div>
                            <div class="flex items-center justify-between pt-1.5 border-t border-border/60 text-[11px]">
                                <button type="button" x-show="silhouettes.length > 0" @click="clearGroup('silhouettes')" class="text-muted hover:text-main font-semibold">Bỏ chọn</button>
                                <button type="button" @click="closeDropdown()" class="ml-auto text-accent font-bold">Đóng</button>
                            </div>
                        </div>
                    </div>

                    {{-- 3. Mục đích sử dụng (multi-select, searchable) --}}
                    <div class="relative min-w-0" @click.outside="if (openDropdown === 'activities') closeDropdown()">
                        <button type="button" @click="toggleDropdown('activities')" :aria-expanded="(openDropdown === 'activities').toString()"
                                :class="activities.length > 0 ? 'border-accent/60 bg-accent/5 text-accent font-bold' : 'border-border bg-surface text-main font-semibold'"
                                class="w-full inline-flex items-center justify-between gap-1.5 px-3 py-2 border rounded-xl text-xs hover:border-accent/60 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                            <span class="truncate">Mục đích</span>
                            <span class="inline-flex items-center gap-1 shrink-0">
                                <span x-show="activities.length > 0" x-text="activities.length" class="inline-flex items-center justify-center min-w-[1.15rem] h-4 px-1 rounded-full bg-accent text-accent-text text-[10px] font-extrabold"></span>
                                <svg class="w-3.5 h-3.5 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </span>
                        </button>
                        <div x-show="openDropdown === 'activities'" x-cloak class="absolute left-0 right-0 z-30 mt-1.5 w-full max-w-full bg-surface-card border border-border rounded-xl shadow-lg p-2 space-y-2">
                            <input type="search" x-ref="activitiesSearch" x-model="dropdownSearch.activities" placeholder="Tìm mục đích sử dụng..."
                                   class="w-full px-2.5 py-1.5 bg-surface border border-border rounded-lg text-xs text-main placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-accent">
                            <div class="max-h-52 overflow-y-auto space-y-1 pr-0.5">
                                @foreach ($filterActivities as $item)
                                    <label data-filter-label="{{ $item['label'] }}"
                                           data-filter-value="{{ $item['id'] }}"
                                           x-show="matchesDropdownSearch('activities', $el.dataset.filterLabel)"
                                           :class="isSelected('activities', $el.dataset.filterValue) ? 'bg-accent/10 text-accent font-bold border-accent/30' : 'text-main hover:bg-surface border-transparent'"
                                           class="flex items-center gap-2.5 px-2.5 py-1.5 rounded-lg border cursor-pointer text-xs transition-colors select-none focus-within:ring-2 focus-within:ring-accent">
                                        <input type="checkbox" name="activities[]" value="{{ $item['id'] }}"
                                               :checked="isSelected('activities', $el.value)"
                                               @change="toggleSelection('activities', $el.value)"
                                               class="sr-only">
                                        <span :class="isSelected('activities', $el.closest('label').dataset.filterValue) ? 'bg-accent border-accent text-accent-text' : 'bg-surface-card border-border text-transparent'"
                                              class="w-4 h-4 rounded border flex items-center justify-center shrink-0 transition-colors" aria-hidden="true">
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </span>
                                        <span class="flex-1 truncate">{{ $item['label'] }}</span>
                                        @if (! $item['is_active'])
                                            <span class="text-[10px] px-1.5 py-0.5 rounded bg-muted/15 text-muted shrink-0">Ẩn</span>
                                        @endif
                                    </label>
                                @endforeach
                            </div>
                            <div class="flex items-center justify-between pt-1.5 border-t border-border/60 text-[11px]">
                                <button type="button" x-show="activities.length > 0" @click="clearGroup('activities')" class="text-muted hover:text-main font-semibold">Bỏ chọn</button>
                                <button type="button" @click="closeDropdown()" class="ml-auto text-accent font-bold">Đóng</button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Row 3: Management Filters Tray (Option B distinct tray style on its own separate row) --}}
                <div class="flex flex-wrap items-center gap-x-6 gap-y-2 px-3.5 py-2 rounded-xl bg-surface/80 border border-dashed border-border text-xs">
                    {{-- 4. Trạng thái bán --}}
                    <div class="inline-flex items-center gap-2 min-w-0">
                        <label for="filter-status" class="text-[11px] font-semibold text-muted whitespace-nowrap">Trạng thái bán:</label>
                        <select id="filter-status" name="status" x-model="status" @change="applyFilters()"
                                :class="status !== '' ? 'border-accent/60 bg-accent/5 text-accent font-bold' : 'border-border bg-surface-card text-main font-semibold'"
                                class="px-2.5 py-1 border rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-accent">
                            <option value="">Tất cả</option>
                            <option value="active">Đang bán</option>
                            <option value="inactive">Ngừng bán</option>
                        </select>
                    </div>

                    {{-- 5. Tồn kho có thể bán --}}
                    <div class="inline-flex items-center gap-2 min-w-0">
                        <label for="filter-stock" class="text-[11px] font-semibold text-muted whitespace-nowrap">Tồn kho:</label>
                        <select id="filter-stock" name="stock" x-model="stock" @change="applyFilters()"
                                :class="stock !== '' ? 'border-accent/60 bg-accent/5 text-accent font-bold' : 'border-border bg-surface-card text-main font-semibold'"
                                class="px-2.5 py-1 border rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-accent">
                            <option value="">Tất cả</option>
                            <option value="in_stock">Còn hàng</option>
                            <option value="out_of_stock">Hết hàng</option>
                        </select>
                    </div>

                    {{-- 6. Nổi bật --}}
                    <div class="inline-flex items-center gap-2 min-w-0">
                        <label for="filter-featured" class="text-[11px] font-semibold text-muted whitespace-nowrap">Nổi bật:</label>
                        <select id="filter-featured" name="featured" x-model="featured" @change="applyFilters()"
                                :class="featured !== '' ? 'border-accent/60 bg-accent/5 text-accent font-bold' : 'border-border bg-surface-card text-main font-semibold'"
                                class="px-2.5 py-1 border rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-accent">
                            <option value="">Tất cả</option>
                            <option value="featured">Đang nổi bật</option>
                            <option value="not_featured">Không nổi bật</option>
                        </select>
                    </div>
                </div>
            </div>

            {{-- Active Filter Chips & Clear All Row --}}
            <div x-show="hasAnyFilter()" x-cloak class="flex flex-wrap items-center gap-1.5 pt-2 border-t border-border/60 text-xs">
                <span class="text-[11px] font-bold text-muted mr-0.5">Đang lọc:</span>
                <template x-for="chip in activeChips()" :key="chip.key">
                    <span class="inline-flex items-center gap-1 pl-2.5 pr-1.5 py-0.5 rounded-full bg-accent/10 text-accent border border-accent/25 text-[11px] font-semibold">
                        <span class="text-muted font-medium" x-text="chip.groupLabel + ':'"></span>
                        <span class="font-bold text-main" x-text="chip.label"></span>
                        <button type="button" @click="removeChip(chip)" :aria-label="'Bỏ điều kiện ' + chip.groupLabel + ' ' + chip.label"
                                class="ml-0.5 inline-flex items-center justify-center w-4 h-4 rounded-full hover:bg-accent hover:text-accent-text transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </span>
                </template>
                <button type="button" @click="clearAllFilters()"
                        class="ml-auto inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-bold text-muted hover:text-error hover:bg-error-bg/50 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                    <span>Xóa tất cả</span>
                </button>
            </div>
        </form>

        <x-admin.ajax-feedback />

        <div id="admin-list-region" x-ref="region" @click="handleRegionClick($event)" :class="{ 'opacity-60 pointer-events-none': filterLoading }" class="bg-surface-card rounded-2xl border border-border overflow-hidden shadow-sm transition-opacity">
        <div class="px-4 py-2.5 border-b border-border bg-surface/50 flex flex-wrap items-center justify-between gap-2 text-xs">
            <span class="text-muted">
                Tìm thấy <strong class="text-main font-extrabold tabular-nums">{{ $products->total() }}</strong> sản phẩm
                @if ($hasActiveFilters)
                    <span class="text-accent font-semibold">(đang áp dụng bộ lọc)</span>
                @endif
            </span>
        </div>

        {{-- Mobile card layout (< md) --}}
        <div class="md:hidden divide-y divide-border">
            @forelse ($products as $product)
                @php($thumbnail = $product->colors->first()?->thumbnail)
                @php($stock = (int) ($product->sellable_stock ?? 0))
                <article class="p-4 space-y-3">
                    <div class="flex items-start gap-3">
                        <a href="{{ route('admin.products.show', $product) }}" class="block w-14 h-14 shrink-0 rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                            @if ($thumbnail)
                                <img src="{{ $thumbnail->thumb_64_url ?? $thumbnail->thumbnail_url ?? $images->imageUrl($thumbnail) }}" alt="{{ $product->name }}" class="w-14 h-14 object-cover rounded-xl border border-border bg-surface" loading="lazy" decoding="async" width="56" height="56">
                            @else
                                <div class="flex w-14 h-14 items-center justify-center rounded-xl border border-border bg-surface text-[9px] text-muted text-center px-1 leading-tight">Chưa có ảnh</div>
                            @endif
                        </a>
                        <div class="min-w-0 flex-1 space-y-1">
                            <div class="flex items-start justify-between gap-2">
                                <a href="{{ route('admin.products.show', $product) }}" class="font-bold text-main hover:text-accent hover:underline text-sm leading-snug break-words transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent rounded">
                                    {{ $product->name }}
                                </a>
                                @if ($product->is_active)
                                    <span class="shrink-0 inline-flex items-center gap-1 whitespace-nowrap bg-success-bg text-success border border-success/30 px-2 py-0.5 rounded-full font-bold text-[11px]">
                                        <span class="w-1.5 h-1.5 rounded-full bg-success" aria-hidden="true"></span>
                                        Đang bán
                                    </span>
                                @else
                                    <span class="shrink-0 inline-flex items-center gap-1 whitespace-nowrap bg-muted/15 text-muted border border-border px-2 py-0.5 rounded-full font-bold text-[11px]">
                                        <span class="w-1.5 h-1.5 rounded-full bg-muted" aria-hidden="true"></span>
                                        Đã ẩn
                                    </span>
                                @endif
                            </div>
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px]">
                                <span class="text-muted font-mono truncate max-w-[12rem]">{{ $product->slug }}</span>
                                <a href="{{ route('admin.products.show', $product) }}" class="font-semibold text-muted hover:text-accent underline decoration-border hover:decoration-accent underline-offset-2 whitespace-nowrap focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent rounded">
                                    Chi tiết
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-2 text-xs pt-1">
                        <div class="font-mono tabular-nums">
                            @if ($product->promotion_price !== null && $product->promotion_price < $product->base_price)
                                <span class="font-extrabold text-accent text-sm">{{ number_format($product->promotion_price, 0, ',', '.') }} ₫</span>
                                <span class="text-[11px] text-muted line-through ml-1.5">{{ number_format($product->base_price, 0, ',', '.') }} ₫</span>
                            @else
                                <span class="font-extrabold text-main text-sm">{{ number_format($product->base_price, 0, ',', '.') }} ₫</span>
                            @endif
                        </div>
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="text-[11px] font-semibold text-muted">{{ $product->category?->gender_group?->label() ?? '—' }}</span>
                            <span @class([
                                'inline-flex items-center whitespace-nowrap border px-2 py-0.5 rounded-lg font-mono tabular-nums font-bold text-[11px]',
                                'bg-surface text-main border-border' => $stock > 0,
                                'bg-error-bg text-error border-error/30' => $stock <= 0,
                            ])>{{ $stock }} đôi</span>
                            <span class="inline-flex items-center whitespace-nowrap bg-surface text-muted border border-border px-2 py-0.5 rounded text-[11px] font-mono tabular-nums">{{ $product->variants_count }} biến thể</span>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-border/60">
                        <x-admin.status-switch
                            :active="$product->is_active"
                            :action="$product->is_active ? route('admin.products.deactivate', $product) : route('admin.products.activate', $product)"
                            :toggle-key="'product-'.$product->id"
                            active-label="Đang bán"
                            inactive-label="Ngừng bán"
                            :action-label="($product->is_active ? 'Ngừng bán ' : 'Mở bán ').$product->name"
                        />

                        <div class="flex items-center gap-1.5">
                            <a class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-muted hover:text-main hover:bg-surface font-semibold text-xs transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent"
                               href="{{ route('admin.products.duplicate', $product) }}">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75" />
                                </svg>
                                <span>Nhân bản</span>
                            </a>
                            <a class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-accent/10 text-accent hover:bg-accent hover:text-accent-text font-bold text-xs transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent"
                               href="{{ route('admin.products.edit', $product) }}">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                                </svg>
                                <span>Sửa</span>
                            </a>
                        </div>
                    </div>
                </article>
            @empty
                <div class="p-10 text-center text-xs text-muted space-y-2">
                    <p>{{ $hasActiveFilters ? 'Không có sản phẩm nào phù hợp với bộ lọc hiện tại.' : 'Chưa có sản phẩm.' }}</p>
                    @if ($hasActiveFilters)
                        <button type="button" data-clear-filters class="px-3.5 py-1.5 rounded-lg border border-border bg-surface text-main font-bold hover:bg-border/40 transition-colors">
                            Xóa bộ lọc
                        </button>
                    @endif
                </div>
            @endforelse
        </div>

        {{-- Desktop table layout (>= md) --}}
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left text-xs text-muted border-collapse">
                <thead class="bg-surface text-main font-bold border-b border-border">
                    <tr>
                        <th class="px-3 py-3.5 w-16 whitespace-nowrap">Ảnh</th>
                        <th class="px-3 py-3.5 min-w-[12rem]">Sản phẩm</th>
                        <th class="px-3 py-3.5 whitespace-nowrap">Đối tượng</th>
                        <th class="px-3 py-3.5 whitespace-nowrap">Giá bán</th>
                        <th class="px-3 py-3.5 text-center whitespace-nowrap">Tồn kho</th>
                        <th class="px-3 py-3.5 text-left whitespace-nowrap">Trạng thái</th>
                        <th class="px-3 py-3.5 text-center whitespace-nowrap">Biến thể</th>
                        <th class="px-3 py-3.5 whitespace-nowrap hidden 2xl:table-cell">Ngày tạo</th>
                        <th class="px-3 py-3.5 text-right whitespace-nowrap">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border font-medium">
                @forelse ($products as $product)
                    @php($thumbnail = $product->colors->first()?->thumbnail)
                    @php($stock = (int) ($product->sellable_stock ?? 0))
                    <tr class="hover:bg-surface/50 transition-colors">
                        <td class="px-3 py-3.5 whitespace-nowrap">
                            <a href="{{ route('admin.products.show', $product) }}" class="block w-12 h-12 rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                                @if ($thumbnail)
                                    <img src="{{ $thumbnail->thumb_64_url ?? $thumbnail->thumbnail_url ?? $images->imageUrl($thumbnail) }}" alt="{{ $product->name }}" class="w-12 h-12 object-cover rounded-xl border border-border bg-surface" loading="lazy" decoding="async" width="48" height="48">
                                @else
                                    <div class="flex w-12 h-12 items-center justify-center rounded-xl border border-border bg-surface text-[9px] text-muted text-center px-0.5 leading-tight">Chưa có ảnh</div>
                                @endif
                            </a>
                        </td>
                        <td class="px-3 py-3.5 min-w-[12rem] max-w-[17rem]">
                            <a href="{{ route('admin.products.show', $product) }}" class="font-bold text-main hover:text-accent hover:underline text-sm block leading-snug break-words transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent rounded">
                                {{ $product->name }}
                            </a>
                            <div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                <span class="text-[11px] text-muted font-mono truncate max-w-[11rem]">{{ $product->slug }}</span>
                                <a href="{{ route('admin.products.show', $product) }}" class="text-[11px] font-semibold text-muted hover:text-accent underline decoration-border hover:decoration-accent underline-offset-2 transition-colors whitespace-nowrap focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent rounded">
                                    Chi tiết
                                </a>
                            </div>
                        </td>
                        <td class="px-3 py-3.5 text-main font-bold whitespace-nowrap">{{ $product->category?->gender_group?->label() ?? '—' }}</td>
                        <td class="px-3 py-3.5 whitespace-nowrap font-mono tabular-nums">
                            @if ($product->promotion_price !== null && $product->promotion_price < $product->base_price)
                                <span class="font-extrabold text-accent text-sm block">{{ number_format($product->promotion_price, 0, ',', '.') }} ₫</span>
                                <span class="text-[11px] text-muted line-through">{{ number_format($product->base_price, 0, ',', '.') }} ₫</span>
                            @else
                                <span class="font-extrabold text-main text-sm block">{{ number_format($product->base_price, 0, ',', '.') }} ₫</span>
                            @endif
                        </td>
                        <td class="px-3 py-3.5 text-center whitespace-nowrap">
                            <span @class([
                                'inline-flex items-center justify-center whitespace-nowrap border px-2.5 py-1 rounded-lg font-mono tabular-nums font-bold text-[11px]',
                                'bg-surface text-main border-border' => $stock > 0,
                                'bg-error-bg text-error border-error/30' => $stock <= 0,
                            ])>{{ $stock }} đôi</span>
                        </td>
                        <td class="px-3 py-3.5 text-left whitespace-nowrap">
                            <x-admin.status-switch
                                :active="$product->is_active"
                                :action="$product->is_active ? route('admin.products.deactivate', $product) : route('admin.products.activate', $product)"
                                :toggle-key="'product-'.$product->id"
                                active-label="Đang bán"
                                inactive-label="Ngừng bán"
                                :action-label="($product->is_active ? 'Ngừng bán ' : 'Mở bán ').$product->name"
                            />
                        </td>
                        <td class="px-3 py-3.5 text-center whitespace-nowrap">
                            <span class="inline-flex items-center whitespace-nowrap bg-surface text-muted border border-border px-2.5 py-0.5 rounded text-[11px] font-mono tabular-nums">{{ $product->variants_count }}</span>
                        </td>
                        <td class="px-3 py-3.5 whitespace-nowrap text-xs text-muted hidden 2xl:table-cell">{{ $product->created_at?->setTimezone(config('store.business_timezone'))->format('d/m/Y') }}</td>
                        <td class="px-3 py-3.5 text-right whitespace-nowrap">
                            <div class="inline-flex items-center justify-end gap-1.5">
                                <a class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-accent/10 text-accent hover:bg-accent hover:text-accent-text font-bold text-xs transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent"
                                   href="{{ route('admin.products.edit', $product) }}">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                                    </svg>
                                    <span>Sửa</span>
                                </a>
                                <a class="inline-flex items-center gap-1 px-2 py-1.5 rounded-lg text-muted hover:text-main hover:bg-surface font-semibold text-xs transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent"
                                   href="{{ route('admin.products.duplicate', $product) }}"
                                   title="Nhân bản sản phẩm {{ $product->name }}">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75" />
                                    </svg>
                                    <span>Nhân bản</span>
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="p-12 text-center text-xs text-muted">
                            <p>{{ $hasActiveFilters ? 'Không có sản phẩm nào phù hợp với bộ lọc hiện tại.' : 'Chưa có sản phẩm.' }}</p>
                            @if ($hasActiveFilters)
                                <button type="button" data-clear-filters class="mt-3 px-3.5 py-1.5 rounded-lg border border-border bg-surface text-main font-bold hover:bg-border/40 transition-colors">
                                    Xóa bộ lọc
                                </button>
                            @endif
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <x-ui.pagination :paginator="$products" />
        </div>
    </div>
@endsection
