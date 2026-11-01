@props(['categories' => [], 'search' => '', 'searchUrl' => null, 'paginator' => null])
<section class="space-y-4">
    <h2 class="text-xl font-semibold">Danh mục</h2>
    @if ($searchUrl)
        <form action="{{ $searchUrl }}" method="GET" class="flex flex-wrap gap-2">
            <label class="sr-only" for="category-search">Tìm danh mục</label><input class="input flex-1" id="category-search" name="search" value="{{ $search }}">
            <x-ui.button type="submit">Tìm</x-ui.button>
        </form>
    @endif
    <div class="overflow-x-auto"><table class="w-full border-collapse text-left text-sm"><thead><tr class="border-b"><th class="p-2">Tên</th><th class="p-2">Nhóm</th><th class="p-2">Trạng thái</th><th class="p-2">Thứ tự</th></tr></thead><tbody>
    @forelse ($categories as $category)
        <tr class="border-b"><td class="p-2">{{ $category['name'] ?? '' }}</td><td class="p-2">{{ $category['gender_group'] ?? '' }}</td><td class="p-2">{{ ($category['is_active'] ?? false) ? 'Đang hiển thị' : 'Đang ẩn' }}</td><td class="p-2">{{ $category['display_order'] ?? '' }}</td></tr>
    @empty <tr><td class="p-4 text-zinc-500" colspan="4">Chưa có danh mục.</td></tr> @endforelse
    </tbody></table></div>
    @if ($paginator)<x-ui.pagination :paginator="$paginator" />@endif
</section>
