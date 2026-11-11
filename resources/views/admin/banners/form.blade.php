@extends('layouts.admin')
@section('title', $record->exists ? 'Chỉnh Sửa Banner' : 'Tạo Banner Mới')
@section('eyebrow', 'QUẢN TRỊ TRANG CHỦ')
@section('content')
<section class="max-w-4xl mx-auto space-y-6" aria-label="Form banner trang chủ"
         x-data="{
             title: @js(old('title', $record->title ?? '')),
             subtitle: @js(old('subtitle', $record->subtitle ?? '')),
             cta_text: @js(old('cta_text', $record->cta_text ?? '')),
             desktopPreview: @js($record->exists ? $record->imageUrl() : ''),
             mobilePreview: @js($record->exists && $record->mobile_image_path ? $record->mobileImageUrl() : ''),
             previewMode: 'desktop',
             handleFile(event, type) {
                 const file = event.target.files[0];
                 if (file) {
                     const url = URL.createObjectURL(file);
                     if (type === 'desktop') this.desktopPreview = url;
                     if (type === 'mobile') this.mobilePreview = url;
                 }
             }
         }">

    <div class="flex items-center justify-between">
        <div>
            <a href="{{ route('admin.banners.index') }}" class="text-xs font-semibold text-accent hover:underline flex items-center gap-1 mb-1">
                ← Quay lại danh sách banner
            </a>
            <h1 class="text-2xl font-extrabold text-main">
                {{ $record->exists ? 'Chỉnh sửa banner' : 'Tạo banner mới' }}
            </h1>
        </div>
    </div>

    @if ($errors->any())
        <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-600 dark:text-rose-400 text-xs">
            <strong class="font-bold block mb-1">Vui lòng kiểm tra lại các thông tin sau:</strong>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Live Preview Box --}}
    <div class="bg-surface-card rounded-2xl border border-border p-4 shadow-sm space-y-3">
        <div class="flex items-center justify-between border-b border-border pb-3">
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-accent"></span>
                <span class="text-xs font-bold uppercase tracking-wider text-main font-mono">Xem trước thời gian thực (Live Preview)</span>
            </div>
            <div class="inline-flex rounded-lg border border-border bg-surface p-0.5 text-xs font-semibold">
                <button type="button" @click="previewMode = 'desktop'" :class="previewMode === 'desktop' ? 'bg-accent text-accent-text shadow-sm' : 'text-muted hover:text-main'" class="px-3 py-1 rounded-md transition-colors">
                    Desktop
                </button>
                <button type="button" @click="previewMode = 'mobile'" :class="previewMode === 'mobile' ? 'bg-accent text-accent-text shadow-sm' : 'text-muted hover:text-main'" class="px-3 py-1 rounded-md transition-colors">
                    Mobile
                </button>
            </div>
        </div>

        {{-- Desktop Frame --}}
        <div x-show="previewMode === 'desktop'" class="relative aspect-[21/9] w-full rounded-xl overflow-hidden border border-border bg-surface flex items-center justify-center">
            <template x-if="desktopPreview">
                <img :src="desktopPreview" alt="Xem trước Desktop" class="w-full h-full object-cover">
            </template>
            <template x-if="!desktopPreview">
                <div class="text-center text-muted text-xs p-6">
                    <svg class="w-8 h-8 mx-auto text-muted/50 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span>Chưa có ảnh desktop (Hãy chọn file ảnh bên dưới)</span>
                </div>
            </template>
            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent flex flex-col justify-end p-6 text-white" x-show="title || subtitle || cta_text">
                <h3 class="text-xl sm:text-2xl font-extrabold leading-tight text-white drop-shadow-sm" x-text="title || 'Tiêu đề banner'"></h3>
                <p class="text-xs sm:text-sm text-white/90 mt-1 max-w-xl line-clamp-2 drop-shadow-sm" x-text="subtitle || ''" x-show="subtitle"></p>
                <div class="mt-3" x-show="cta_text">
                    <span class="inline-block px-5 py-2 bg-accent text-accent-text text-xs font-bold rounded-lg shadow" x-text="cta_text"></span>
                </div>
            </div>
        </div>

        {{-- Mobile Frame --}}
        <div x-show="previewMode === 'mobile'" class="relative max-w-xs mx-auto aspect-[4/3] rounded-xl overflow-hidden border border-border bg-surface flex items-center justify-center">
            <template x-if="mobilePreview || desktopPreview">
                <img :src="mobilePreview || desktopPreview" alt="Xem trước Mobile" class="w-full h-full object-cover">
            </template>
            <template x-if="!mobilePreview && !desktopPreview">
                <div class="text-center text-muted text-xs p-4">
                    <span>Chưa có ảnh mobile</span>
                </div>
            </template>
            <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/35 to-transparent flex flex-col justify-end p-4 text-white" x-show="title || subtitle || cta_text">
                <h3 class="text-base font-extrabold leading-tight text-white drop-shadow-sm" x-text="title || 'Tiêu đề banner'"></h3>
                <p class="text-[11px] text-white/90 mt-0.5 line-clamp-1 drop-shadow-sm" x-text="subtitle || ''" x-show="subtitle"></p>
                <div class="mt-2" x-show="cta_text">
                    <span class="inline-block px-3 py-1.5 bg-accent text-accent-text text-[10px] font-bold rounded shadow" x-text="cta_text"></span>
                </div>
            </div>
        </div>
    </div>

    {{-- Edit Form --}}
    <form method="POST"
          action="{{ $record->exists ? route('admin.banners.update', $record) : route('admin.banners.store') }}"
          enctype="multipart/form-data"
          class="bg-surface-card rounded-2xl border border-border p-6 shadow-sm space-y-6">
        @csrf
        @if ($record->exists)
            @method('PUT')
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Title --}}
            <div class="space-y-1.5 md:col-span-2">
                <label for="title" class="block text-xs font-bold text-main">
                    Tiêu đề banner <span class="text-rose-500">*</span>
                </label>
                <input type="text" id="title" name="title" x-model="title" required maxlength="150"
                       class="input w-full text-xs bg-surface border-border text-main"
                       placeholder="Ví dụ: BỨT PHÁ MỌI GIỚI HẠN VỚI PEGASUS 41">
                <p class="text-[11px] text-muted">Tiêu đề lớn hiển thị trên banner (tối đa 150 ký tự).</p>
            </div>

            {{-- Subtitle --}}
            <div class="space-y-1.5 md:col-span-2">
                <label for="subtitle" class="block text-xs font-bold text-main">
                    Phụ đề mô tả
                </label>
                <textarea id="subtitle" name="subtitle" x-model="subtitle" rows="2" maxlength="255"
                          class="input w-full text-xs bg-surface border-border text-main py-2"
                          placeholder="Ví dụ: Đệm phản hồi năng lượng êm ái, nâng niu từng sải chân chạy bộ."></textarea>
                <p class="text-[11px] text-muted">Dòng thông điệp phụ bên dưới tiêu đề (tùy chọn, tối đa 255 ký tự).</p>
            </div>

            {{-- Desktop Image --}}
            <div class="space-y-1.5">
                <label for="image" class="block text-xs font-bold text-main">
                    Ảnh phiên bản Desktop {{ $record->exists ? '' : '*' }}
                </label>
                <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp"
                       @change="handleFile($event, 'desktop')"
                       {{ $record->exists ? '' : 'required' }}
                       class="block w-full text-xs text-muted file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-accent file:text-accent-text hover:file:bg-accent-hover cursor-pointer border border-border rounded-xl bg-surface p-1">
                <p class="text-[11px] text-muted">Khuyến nghị kích thước 1920x640px hoặc 1600x600px. Tối đa 2MB (JPG, PNG, WebP).</p>
            </div>

            {{-- Mobile Image --}}
            <div class="space-y-1.5">
                <label for="mobile_image" class="block text-xs font-bold text-main">
                    Ảnh phiên bản Mobile (Tùy chọn)
                </label>
                <input type="file" id="mobile_image" name="mobile_image" accept="image/jpeg,image/png,image/webp"
                       @change="handleFile($event, 'mobile')"
                       class="block w-full text-xs text-muted file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-surface file:border file:border-border file:text-main hover:file:bg-surface/80 cursor-pointer border border-border rounded-xl bg-surface p-1">
                <p class="text-[11px] text-muted">Khuyến nghị 800x800px hoặc 800x600px. Nếu không chọn, hệ thống tự crop tâm từ ảnh Desktop.</p>
            </div>

            {{-- Link URL --}}
            <div class="space-y-1.5">
                <label for="link_url" class="block text-xs font-bold text-main">
                    Đường dẫn liên kết (Link URL)
                </label>
                <input type="text" id="link_url" name="link_url" value="{{ old('link_url', $record->link_url) }}" maxlength="500"
                       class="input w-full text-xs bg-surface border-border text-main font-mono"
                       placeholder="/shoes?gender=men hoặc https://...">
                <p class="text-[11px] text-muted">Đường dẫn khi khách click banner hoặc nút CTA. Hỗ trợ relative link (/shoes) hoặc https://.</p>
            </div>

            {{-- CTA Text --}}
            <div class="space-y-1.5">
                <label for="cta_text" class="block text-xs font-bold text-main">
                    Nhãn nút bấm (CTA Text)
                </label>
                <input type="text" id="cta_text" name="cta_text" x-model="cta_text" maxlength="50"
                       class="input w-full text-xs bg-surface border-border text-main"
                       placeholder="Ví dụ: Khám phá ngay, Mua ngay, Xem ưu đãi">
                <p class="text-[11px] text-muted">Nếu để trống, banner sẽ chỉ hiển thị ảnh mà không có nút bấm CTA.</p>
            </div>

            {{-- Sort Order --}}
            <div class="space-y-1.5">
                <label for="sort_order" class="block text-xs font-bold text-main">
                    Thứ tự hiển thị <span class="text-rose-500">*</span>
                </label>
                <input type="number" id="sort_order" name="sort_order" value="{{ old('sort_order', $record->sort_order ?? 1) }}" min="0" max="65535" required
                       class="input w-full text-xs bg-surface border-border text-main">
                <p class="text-[11px] text-muted">Số nhỏ hơn sẽ xuất hiện trước trong chuỗi Carousel.</p>
            </div>

            {{-- Status is_active --}}
            <div class="space-y-1.5">
                <label for="is_active" class="block text-xs font-bold text-main">
                    Trạng thái hiển thị <span class="text-rose-500">*</span>
                </label>
                <select id="is_active" name="is_active" required class="select w-full text-xs bg-surface border-border text-main">
                    <option value="1" {{ old('is_active', (string) ($record->is_active ?? '1')) === '1' ? 'selected' : '' }}>
                        Bật — Hiển thị trên trang chủ
                    </option>
                    <option value="0" {{ old('is_active', (string) ($record->is_active ?? '1')) === '0' ? 'selected' : '' }}>
                        Tắt — Đang ẩn
                    </option>
                </select>
                <p class="text-[11px] text-muted">Khi tắt, banner sẽ không xuất hiện trong Carousel của khách hàng.</p>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-border">
            <a href="{{ route('admin.banners.index') }}" class="px-5 py-2.5 rounded-xl border border-border text-muted hover:text-main text-xs font-bold transition-colors">
                Hủy bỏ
            </a>
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-accent hover:bg-accent-hover text-accent-text text-xs font-bold transition-colors shadow-md">
                {{ $record->exists ? 'Lưu thay đổi' : 'Tạo banner' }}
            </button>
        </div>
    </form>
</section>
@endsection
