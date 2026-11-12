@props(['suppliers' => [], 'searchUrl' => null, 'search' => '', 'paginator' => null])
<section x-data="adminStatusToggle()" class="space-y-6" aria-label="Nhà cung cấp">
    @if ($searchUrl)
        <x-admin.search-form :action="$searchUrl" id="supplier-search" label="Tìm nhà cung cấp" placeholder="Tìm kiếm theo mã, tên nhà cung cấp..." :value="$search" />
    @endif
    <x-admin.ajax-feedback />
    <div id="admin-list-region" x-ref="region" class="bg-surface-card rounded-2xl border border-border overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-muted border-collapse">
                <thead class="bg-surface text-main font-bold border-b border-border">
                    <tr>
                        <th class="p-4 w-32">Mã</th>
                        <th class="p-4">Tên</th>
                        <th class="p-4">Liên hệ</th>
                        <th class="p-4 text-center">Trạng thái</th>
                        <th class="p-4 text-right">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border font-medium">
                @forelse ($suppliers as $supplier)
                    <tr class="hover:bg-surface/50 transition-colors">
                        <td class="p-4 font-mono font-bold text-accent text-xs">{{ $supplier['code'] ?? '' }}</td>
                        <td class="p-4">
                            <strong class="font-bold text-main text-sm block">{{ $supplier['name'] ?? '' }}</strong>
                            @if ($supplier['address'] ?? null)<span class="block text-[11px] text-muted mt-0.5">{{ $supplier['address'] }}</span>@endif
                        </td>
                        <td class="p-4">
                            <span class="font-semibold text-main text-xs block">{{ $supplier['phone'] ?? '' }}</span>
                            @if ($supplier['email'] ?? null)<span class="block text-[11px] text-muted">{{ $supplier['email'] }}</span>@endif
                        </td>
                        <td class="p-4 text-center">
                            @if ($supplier['is_active'] ?? false)
                                <span class="bg-success-bg text-success border border-success/30 px-2.5 py-0.5 rounded-full font-bold text-[11px]">Hoạt động</span>
                            @else
                                <span class="bg-muted/15 text-muted px-2.5 py-0.5 rounded-full font-bold text-[11px]">Đã ngừng</span>
                            @endif
                        </td>
                        <td class="p-4 text-right whitespace-nowrap">
                            <div class="inline-flex items-center gap-1.5">
                                @if ($supplier['editUrl'] ?? null)
                                    <a class="px-2.5 py-1 text-accent hover:bg-surface rounded-lg font-bold transition-colors" href="{{ $supplier['editUrl'] }}">Sửa</a>
                                @endif
                                @if ($supplier['deactivateUrl'] ?? null)
                                    <form action="{{ $supplier['deactivateUrl'] }}" method="POST" @submit.prevent="submitToggle($event, 'supplier-{{ $supplier['id'] ?? $loop->index }}')" class="inline-block">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" :disabled="isBusy()" class="px-2.5 py-1 text-error hover:bg-error-bg rounded-lg font-bold transition-colors disabled:opacity-40 disabled:cursor-not-allowed">Ngừng hoạt động</button>
                                    </form>
                                @elseif ($supplier['activateUrl'] ?? null)
                                    <form action="{{ $supplier['activateUrl'] }}" method="POST" @submit.prevent="submitToggle($event, 'supplier-{{ $supplier['id'] ?? $loop->index }}')" class="inline-block">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" :disabled="isBusy()" class="px-2.5 py-1 text-success hover:bg-success-bg rounded-lg font-bold transition-colors disabled:opacity-40 disabled:cursor-not-allowed">Kích hoạt</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td class="p-12 text-center text-xs text-muted" colspan="5">Chưa có nhà cung cấp.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($paginator)<x-ui.pagination :paginator="$paginator" />@endif
    </div>
</section>
