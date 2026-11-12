<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ClassificationGroup;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ClassificationRequest;
use App\Models\Classification;
use App\Services\ClassificationManagementService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClassificationController extends Controller
{
    public function index(Request $request, ClassificationManagementService $service): View
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'group' => ['nullable', 'string', 'in:SILHOUETTE,ACTIVITY'],
            'active' => ['nullable', 'in:0,1'],
            'home_nav' => ['nullable', 'in:0,1'],
        ]);

        $query = Classification::query()
            ->withCount('products')
            ->when($data['search'] ?? null, fn ($q, $s) => $q->where(fn ($sub) => $sub->where('name', 'like', '%'.$s.'%')->orWhere('slug', 'like', '%'.$s.'%')))
            ->when($data['group'] ?? null, fn ($q, $g) => $q->where('classification_group', $g))
            ->when(isset($data['active']), fn ($q) => $q->where('is_active', $data['active']))
            ->when(isset($data['home_nav']), fn ($q) => $q->where('show_in_home_nav', $data['home_nav']));

        $paginator = $query->orderBy('classification_group')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.classifications.index', [
            'paginator' => $paginator,
            'search' => $data['search'] ?? '',
            'group' => $data['group'] ?? '',
            'active' => $data['active'] ?? '',
            'homeNav' => $data['home_nav'] ?? '',
            'homeNavCounts' => $service->activeHomeNavCountsByGroup(),
            'homeNavLimit' => ClassificationManagementService::MAX_HOME_NAV_PER_GROUP,
        ]);
    }

    public function create(ClassificationManagementService $service): View
    {
        return $this->form(new Classification, $service);
    }

    public function edit(Classification $classification, ClassificationManagementService $service): View
    {
        return $this->form($classification, $service);
    }

    private function form(Classification $record, ClassificationManagementService $service): View
    {
        return view('admin.classifications.form', [
            'record' => $record,
            'groups' => array_map(fn ($g) => ['value' => $g->value, 'label' => $g->label()], ClassificationGroup::cases()),
            'productsCount' => $record->exists ? $record->products()->count() : 0,
            'homeNavCounts' => $service->activeHomeNavCountsByGroup(),
            'homeNavLimit' => ClassificationManagementService::MAX_HOME_NAV_PER_GROUP,
        ]);
    }

    public function store(ClassificationRequest $request, ClassificationManagementService $service): RedirectResponse
    {
        $service->save($request->user(), null, $request->validated());

        return to_route('admin.classifications.index')->with('status', 'Đã tạo nhãn phân loại mới.');
    }

    public function update(ClassificationRequest $request, Classification $classification, ClassificationManagementService $service): RedirectResponse
    {
        $service->save($request->user(), $classification, $request->validated());

        return to_route('admin.classifications.index')->with('status', 'Đã cập nhật nhãn phân loại.');
    }

    public function toggleActive(Request $request, Classification $classification, ClassificationManagementService $service): RedirectResponse
    {
        $updated = $service->toggleActive($request->user(), $classification);
        $statusText = $updated->is_active ? 'Đã kích hoạt nhãn.' : 'Đã chuyển nhãn sang trạng thái tạm ngưng.';

        return back(fallback: route('admin.classifications.index'))->with('status', $statusText);
    }

    public function toggleHomeNav(Request $request, Classification $classification, ClassificationManagementService $service): RedirectResponse
    {
        $updated = $service->toggleHomeNav($request->user(), $classification);
        $statusText = $updated->show_in_home_nav
            ? 'Đã chọn hiển thị nhãn ở menu & trang chủ.'
            : 'Đã bỏ hiển thị nhãn khỏi menu & trang chủ.';

        return back(fallback: route('admin.classifications.index'))->with('status', $statusText);
    }

    public function destroy(Request $request, Classification $classification, ClassificationManagementService $service): RedirectResponse
    {
        $service->delete($request->user(), $classification);

        return to_route('admin.classifications.index')->with('status', 'Đã xóa nhãn phân loại thành công.');
    }
}
