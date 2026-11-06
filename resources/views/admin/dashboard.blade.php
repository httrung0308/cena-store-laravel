@extends('layouts.admin')

@section('title', 'Bảng Điều Khiển')
@section('eyebrow', 'QUẢN TRỊ HỆ THỐNG')

@php
    $user = auth()->user();
    $modules = [
        ['route' => 'admin.reports.index', 'label' => 'Dashboard và báo cáo', 'description' => 'Doanh thu, giá vốn, lợi nhuận, bán chạy và cảnh báo tồn kho'],
        ['route' => 'admin.categories.index', 'label' => 'Quản lý danh mục', 'description' => 'Danh mục hai cấp theo nhóm Nam, Nữ, Trẻ em, Unisex'],
        ['route' => 'admin.suppliers.index', 'label' => 'Quản lý nhà cung cấp', 'description' => 'Thông tin liên hệ và trạng thái hợp tác'],
        ['route' => 'admin.products.index', 'label' => 'Quản lý sản phẩm', 'description' => 'Sản phẩm, phối màu, size và ảnh'],
        ['route' => 'admin.stock-imports.index', 'label' => 'Phiếu nhập kho', 'description' => 'Tạo phiếu nháp, ghi sổ để cộng tồn kho'],
        ['route' => 'admin.orders.index', 'label' => 'Quản lý đơn hàng', 'description' => 'Xác nhận, đóng gói, giao hàng, thu COD và hủy đơn'],
        ['route' => 'admin.reviews.index', 'label' => 'Quản lý đánh giá', 'description' => 'Phản hồi, ẩn hoặc hiện đánh giá của khách'],
    ];
    $isAdmin = $user->hasRole(App\Enums\RoleSlug::Admin);
@endphp

@section('content')
    <div class="flex flex-col gap-8">
        <p class="text-sm text-muted">Chào mừng đến trang quản trị. Quản lý cửa hàng của bạn tại đây.</p>

        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            <article class="admin-card flex flex-col justify-between">
                <div>
                    <h2 class="text-base font-bold text-main">Thông tin tài khoản</h2>
                    <div class="mt-3 space-y-1.5 text-xs text-muted">
                        <p>Họ tên: <span class="font-semibold text-main">{{ $user->full_name }}</span></p>
                        <p>Email: <span class="break-all font-semibold text-main">{{ $user->email }}</span></p>
                        <p>Vai trò: <span class="font-semibold text-accent">{{ $user->roles->map(fn ($role) => $role->slug->label())->join(', ') }}</span></p>
                    </div>
                </div>
            </article>

            <article class="admin-card flex flex-col justify-between">
                <div>
                    <h2 class="text-base font-bold text-main">Thao tác nhanh</h2>
                    <div class="mt-3 space-y-2">
                        <a href="{{ route('home') }}" class="btn-pill w-full text-xs">Xem cửa hàng</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn-pill w-full text-xs hover:border-error hover:text-error">Đăng xuất</button>
                        </form>
                    </div>
                </div>
            </article>

            <article class="admin-card flex flex-col justify-between">
                <div>
                    <h2 class="text-base font-bold text-main">Trạng thái hệ thống</h2>
                    <div class="mt-4 flex items-center justify-between">
                        <span class="text-xs text-muted">Hệ thống</span>
                        <span class="badge bg-success-bg border border-success/30 text-success-text">Đang hoạt động</span>
                    </div>
                </div>
            </article>
        </div>

        <div class="rounded-2xl border border-border bg-surface-card p-6 shadow-card sm:p-8">
            <h2 class="text-xl font-bold text-main sm:text-2xl">Các mô-đun quản lý</h2>
            <p class="mt-1 text-xs text-muted">Các chức năng quản lý hiện có:</p>
            <div class="mt-6 grid gap-4 md:grid-cols-2">
                @foreach ($modules as $module)
                    @if (Route::has($module['route']))
                        <a href="{{ route($module['route']) }}" class="group rounded-2xl border border-border bg-surface/30 p-5 transition-colors duration-150 hover:border-accent hover:bg-surface/70">
                            <span class="block text-base font-bold text-main group-hover:text-accent transition-colors">{{ $module['label'] }}</span>
                            <span class="mt-1 block text-xs text-muted leading-relaxed">{{ $module['description'] }}</span>
                        </a>
                    @endif
                @endforeach
                @if ($isAdmin)
                    <a href="{{ route('admin.articles.index') }}" class="group rounded-2xl border border-border bg-surface/30 p-5 transition-colors duration-150 hover:border-accent hover:bg-surface/70">
                        <span class="block text-base font-bold text-main group-hover:text-accent transition-colors">Quản lý bài viết</span>
                        <span class="mt-1 block text-xs text-muted leading-relaxed">Viết, đăng và ẩn bài viết về giày (chỉ Admin)</span>
                    </a>
                    <a href="{{ route('admin.users.index') }}" class="group rounded-2xl border border-border bg-surface/30 p-5 transition-colors duration-150 hover:border-accent hover:bg-surface/70">
                        <span class="block text-base font-bold text-main group-hover:text-accent transition-colors">Quản lý người dùng</span>
                        <span class="mt-1 block text-xs text-muted leading-relaxed">Tài khoản, vai trò và khóa/mở khóa</span>
                    </a>
                @else
                    <span class="cursor-not-allowed rounded-2xl border border-border/50 bg-surface/20 p-5 opacity-50" aria-disabled="true">
                        <span class="block text-base font-bold text-muted">Quản lý người dùng</span>
                        <span class="mt-1 block text-xs text-muted leading-relaxed">Tài khoản, vai trò và khóa/mở khóa (chỉ Admin)</span>
                    </span>
                @endif
            </div>
        </div>
    </div>
@endsection
