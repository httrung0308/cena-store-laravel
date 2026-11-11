<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BannerRequest;
use App\Models\Banner;
use App\Services\BannerManagementService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'active' => ['nullable', 'in:0,1'],
        ]);

        $query = Banner::query()
            ->when($data['search'] ?? null, fn ($q, $s) => $q->where(fn ($sub) => $sub->where('title', 'like', '%'.$s.'%')->orWhere('subtitle', 'like', '%'.$s.'%')))
            ->when(isset($data['active']), fn ($q) => $q->where('is_active', $data['active']));

        $paginator = $query->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.banners.index', [
            'paginator' => $paginator,
            'search' => $data['search'] ?? '',
            'active' => $data['active'] ?? '',
        ]);
    }

    public function create(): View
    {
        return $this->form(new Banner);
    }

    public function store(BannerRequest $request, BannerManagementService $service): RedirectResponse
    {
        $banner = $service->save(
            $request->user(),
            null,
            $request->validated(),
            $request->file('image'),
            $request->file('mobile_image'),
        );

        return redirect()->route('admin.banners.index')->with('success', "Đã tạo banner '{$banner->title}' thành công.");
    }

    public function edit(Banner $banner): View
    {
        return $this->form($banner);
    }

    public function update(BannerRequest $request, Banner $banner, BannerManagementService $service): RedirectResponse
    {
        $service->save(
            $request->user(),
            $banner,
            $request->validated(),
            $request->file('image'),
            $request->file('mobile_image'),
        );

        return redirect()->route('admin.banners.index')->with('success', "Đã cập nhật banner '{$banner->title}' thành công.");
    }

    public function toggleActive(Banner $banner, BannerManagementService $service): RedirectResponse
    {
        $updated = $service->toggleActive(request()->user(), $banner);
        $status = $updated->is_active ? 'kích hoạt' : 'ẩn';

        return back()->with('success', "Đã {$status} banner '{$updated->title}'.");
    }

    public function destroy(Banner $banner, BannerManagementService $service): RedirectResponse
    {
        $title = $banner->title;
        $service->delete(request()->user(), $banner);

        return redirect()->route('admin.banners.index')->with('success', "Đã xóa banner '{$title}' thành công.");
    }

    private function form(Banner $record): View
    {
        return view('admin.banners.form', [
            'record' => $record,
        ]);
    }
}
