@extends('layouts.admin')

@section('title', 'Bảng điều khiển')
@section('eyebrow', 'Trang quản trị')

@php
    $user = auth()->user();
    $modules = [
        ['route' => 'admin.categories.index', 'label' => 'Quản lý danh mục', 'description' => 'Danh mục hai cấp theo nhóm Nam, Nữ, Trẻ em, Unisex'],
        ['route' => 'admin.suppliers.index', 'label' => 'Quản lý nhà cung cấp', 'description' => 'Thông tin liên hệ và trạng thái hợp tác'],
        ['route' => 'admin.products.index', 'label' => 'Quản lý sản phẩm', 'description' => 'Sản phẩm, phối màu, size và ảnh'],
        ['route' => 'admin.stock-imports.index', 'label' => 'Phiếu nhập kho', 'description' => 'Tạo phiếu nháp, ghi sổ để cộng tồn kho'],
        ['route' => 'admin.orders.index', 'label' => 'Quản lý đơn hàng', 'description' => 'Xác nhận, đóng gói, giao hàng, thu COD và hủy đơn'],
    ];
    $isAdmin = $user->hasRole(App\Enums\RoleSlug::Admin);
@endphp

@section('content')
    <div class="flex flex-col gap-10">
        <p class="text-base text-zinc-500">Chào mừng đến trang quản trị. Quản lý cửa hàng của bạn tại đây.</p>

        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            <article class="admin-card">
                <h2 class="text-lg font-semibold">Thông tin tài khoản</h2>
                <div class="mt-3 space-y-2 text-sm text-zinc-500">
                    <p>Họ tên: <span class="font-medium text-zinc-900">{{ $user->full_name }}</span></p>
                    <p>Email: <span class="break-all font-medium text-zinc-900">{{ $user->email }}</span></p>
                    <p>Vai trò: <span class="font-medium text-zinc-900">{{ $user->roles->map(fn ($role) => $role->slug->label())->join(', ') }}</span></p>
                </div>
            </article>

            <article class="admin-card">
                <h2 class="text-lg font-semibold">Thao tác nhanh</h2>
                <div class="mt-3 space-y-2">
                    <a href="{{ route('home') }}" class="btn-pill w-full hover:bg-zinc-900 hover:text-white">Xem cửa hàng</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn-pill w-full hover:bg-zinc-900 hover:text-white">Đăng xuất</button>
                    </form>
                </div>
            </article>

            <article class="admin-card">
                <h2 class="text-lg font-semibold">Trạng thái hệ thống</h2>
                <div class="mt-3 flex items-center justify-between">
                    <span class="text-sm text-zinc-500">Hệ thống</span>
                    <span class="badge bg-green-100 text-green-600">Đang hoạt động</span>
                </div>
            </article>
        </div>

        <div class="rounded-3xl border border-zinc-200 p-8 shadow-sm">
            <h2 class="text-2xl font-semibold">Các mô-đun quản lý</h2>
            <p class="mt-2 text-zinc-500">Các chức năng quản lý hiện có:</p>
            <div class="mt-6 grid gap-4 md:grid-cols-2">
                @foreach ($modules as $module)
                    @if (Route::has($module['route']))
                        <a href="{{ route($module['route']) }}" class="rounded-full border border-zinc-200 bg-white px-6 py-4 text-left font-semibold transition hover:border-zinc-900 hover:bg-zinc-50">
                            <span class="block text-lg">{{ $module['label'] }}</span>
                            <span class="mt-1 block text-sm font-normal text-zinc-500">{{ $module['description'] }}</span>
                        </a>
                    @endif
                @endforeach
                @if ($isAdmin)
                    <a href="{{ route('admin.users.index') }}" class="rounded-full border border-zinc-200 bg-white px-6 py-4 text-left font-semibold transition hover:border-zinc-900 hover:bg-zinc-50">
                        <span class="block text-lg">Quản lý người dùng</span>
                        <span class="mt-1 block text-sm font-normal text-zinc-500">Tài khoản, vai trò và khóa/mở khóa</span>
                    </a>
                @else
                    <span class="cursor-not-allowed rounded-full border border-zinc-100 bg-zinc-50 px-6 py-4 text-left font-semibold opacity-50" aria-disabled="true">
                        <span class="block text-lg">Quản lý người dùng</span>
                        <span class="mt-1 block text-sm font-normal text-zinc-500">Tài khoản, vai trò và khóa/mở khóa (chỉ Admin)</span>
                    </span>
                @endif
            </div>
        </div>
    </div>
@endsection
