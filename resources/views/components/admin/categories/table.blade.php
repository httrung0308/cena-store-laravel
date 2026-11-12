@props(['categories' => [], 'search' => '', 'searchUrl' => null, 'paginator' => null])
@php($groups = collect(App\Enums\GenderGroup::cases())->mapWithKeys(fn ($group) => [$group->value => $group->label()]))
<section x-data="adminStatusToggle()" class="space-y-6" aria-label="Danh mục">
    @if ($searchUrl)
        <x-admin.search-form :action="$searchUrl" id="category-search" label="Tìm danh mục" placeholder="Tìm kiếm theo tên danh mục, slug..." :value="$search" />
    @endif
    <x-admin.ajax-feedback />
    <div id="admin-list-region" x-ref="region" class="bg-surface-card rounded-2xl border border-border overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-muted border-collapse">
                <thead class="bg-surface text-main font-bold border-b border-border">
                    <tr>
                        <th class="p-4">Tên</th>
                        <th class="p-4">Nhóm</th>
                        <th class="p-4 text-center">Thứ tự</th>
                        <th class="p-4 text-center">Trạng thái</th>
                        <th class="p-4 text-right">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border font-medium">
                @forelse ($categories as $category)
                    <tr class="hover:bg-surface/50 transition-colors">
                        <td class="p-4">
                            <strong class="font-bold text-main text-sm block">{{ $category['name'] ?? '' }}</strong>
                            <span class="block text-[11px] text-muted font-mono mt-0.5">{{ $category['slug'] ?? '' }}</span>
                        </td>
                        <td class="p-4 font-bold text-main">{{ $groups[$category['gender_group'] ?? ''] ?? ($category['gender_group'] ?? '') }}</td>
                        <td class="p-4 text-center">
                            <span class="bg-surface text-main border border-border px-2.5 py-1 rounded-lg font-mono font-bold text-[11px]">{{ $category['display_order'] ?? '' }}</span>
                        </td>
                        <td class="p-4 text-center">
                            @if ($category['is_active'] ?? false)
                                <span class="bg-success-bg text-success border border-success/30 px-2.5 py-0.5 rounded-full font-bold text-[11px]">Đang hiển thị</span>
                            @else
                                <span class="bg-muted/15 text-muted px-2.5 py-0.5 rounded-full font-bold text-[11px]">Đang ẩn</span>
                            @endif
                        </td>
                        <td class="p-4 text-right whitespace-nowrap">
                            <div class="inline-flex items-center gap-1.5">
                                @if ($category['editUrl'] ?? null)
                                    <a class="px-2.5 py-1 text-accent hover:bg-surface rounded-lg font-bold transition-colors" href="{{ $category['editUrl'] }}">Sửa</a>
                                @endif
                                @if ($category['deactivateUrl'] ?? null)
                                    <form method="POST" action="{{ $category['deactivateUrl'] }}" @submit.prevent="submitToggle($event, 'category-{{ $category['id'] ?? $loop->index }}')" class="inline-block">
                                        @csrf
                                        @method('PATCH')
                                        <button class="px-2.5 py-1 text-error hover:bg-error-bg rounded-lg font-bold transition-colors disabled:opacity-40 disabled:cursor-not-allowed" :disabled="isBusy()" type="submit">Ẩn</button>
                                    </form>
                                @elseif ($category['activateUrl'] ?? null)
                                    <form method="POST" action="{{ $category['activateUrl'] }}" @submit.prevent="submitToggle($event, 'category-{{ $category['id'] ?? $loop->index }}')" class="inline-block">
                                        @csrf
                                        @method('PATCH')
                                        <button class="px-2.5 py-1 text-success hover:bg-success-bg rounded-lg font-bold transition-colors disabled:opacity-40 disabled:cursor-not-allowed" :disabled="isBusy()" type="submit">Hiện</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td class="p-12 text-center text-xs text-muted" colspan="5">Chưa có danh mục.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($paginator)<x-ui.pagination :paginator="$paginator" />@endif
    </div>
</section>
