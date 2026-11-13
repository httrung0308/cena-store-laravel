@props(['classifications' => [], 'selected' => []])
@php
    $selectedIds = array_map('intval', is_array($selected) ? $selected : []);
    $silhouettes = collect($classifications)->filter(fn ($c) => ($c->classification_group->value ?? $c['classification_group'] ?? '') === 'SILHOUETTE');
    $activities = collect($classifications)->filter(fn ($c) => ($c->classification_group->value ?? $c['classification_group'] ?? '') === 'ACTIVITY');
@endphp

<section class="admin-card space-y-5" aria-label="Phân loại đa trị">
    <div class="flex items-center justify-between border-b border-border pb-3">
        <div>
            <h2 class="text-base font-bold text-main">Phân loại sản phẩm (Đa trị)</h2>
            <p class="text-xs text-muted mt-0.5">Chọn nhãn Kiểu dáng và Mục đích sử dụng giúp khách hàng dễ dàng tìm kiếm và lọc trên Storefront.</p>
        </div>
        <a href="{{ route('admin.classifications.create') }}" target="_blank" class="text-xs font-bold text-accent hover:underline flex items-center gap-1">
            + Thêm nhãn mới ↗
        </a>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        {{-- SILHOUETTE GROUP --}}
        <div class="rounded-xl border border-border bg-surface/30 p-4 space-y-3">
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-main">Kiểu dáng giày (Silhouette)</h3>
            </div>
            
            <div class="space-y-1.5 pt-1 max-h-64 overflow-y-auto pr-1">
                @forelse ($silhouettes as $item)
                    @php
                        $cId = is_object($item) ? $item->id : $item['id'];
                        $cName = is_object($item) ? $item->name : $item['name'];
                        $cActive = is_object($item) ? $item->is_active : $item['is_active'];
                        $isChecked = in_array((int) $cId, $selectedIds, true);
                        $canSelect = $cActive || $isChecked;
                    @endphp
                    <label class="flex items-center justify-between p-2 rounded-lg border border-transparent hover:border-border hover:bg-surface transition-colors cursor-pointer select-none {{ ! $canSelect ? 'opacity-50 cursor-not-allowed' : '' }}">
                        <div class="flex items-center gap-2.5">
                            <input type="checkbox" name="classification_ids[]" value="{{ $cId }}"
                                   class="native-checkbox text-accent focus:ring-accent rounded"
                                   @checked($isChecked)
                                   @disabled(! $canSelect)>
                            <span class="text-xs font-medium text-main">{{ $cName }}</span>
                        </div>
                        @if (! $cActive)
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-muted/15 text-muted">Tạm ngưng</span>
                        @endif
                    </label>
                @empty
                    <p class="text-xs text-muted italic">Chưa có nhãn kiểu dáng nào.</p>
                @endforelse
            </div>
        </div>

        {{-- ACTIVITY GROUP --}}
        <div class="rounded-xl border border-border bg-surface/30 p-4 space-y-3">
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-main">Mục đích sử dụng (Activity)</h3>
            </div>
            
            <div class="space-y-1.5 pt-1 max-h-64 overflow-y-auto pr-1">
                @forelse ($activities as $item)
                    @php
                        $cId = is_object($item) ? $item->id : $item['id'];
                        $cName = is_object($item) ? $item->name : $item['name'];
                        $cActive = is_object($item) ? $item->is_active : $item['is_active'];
                        $isChecked = in_array((int) $cId, $selectedIds, true);
                        $canSelect = $cActive || $isChecked;
                    @endphp
                    <label class="flex items-center justify-between p-2 rounded-lg border border-transparent hover:border-border hover:bg-surface transition-colors cursor-pointer select-none {{ ! $canSelect ? 'opacity-50 cursor-not-allowed' : '' }}">
                        <div class="flex items-center gap-2.5">
                            <input type="checkbox" name="classification_ids[]" value="{{ $cId }}"
                                   class="native-checkbox text-accent focus:ring-accent rounded"
                                   @checked($isChecked)
                                   @disabled(! $canSelect)>
                            <span class="text-xs font-medium text-main">{{ $cName }}</span>
                        </div>
                        @if (! $cActive)
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-muted/15 text-muted">Tạm ngưng</span>
                        @endif
                    </label>
                @empty
                    <p class="text-xs text-muted italic">Chưa có nhãn mục đích nào.</p>
                @endforelse
            </div>
        </div>
    </div>
    <x-forms.field-error name="classification_ids" />
</section>
