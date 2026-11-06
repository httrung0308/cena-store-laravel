@props(['title', 'subtitle', 'image', 'right' => false, 'id'])
<section id="collection-{{ $id }}" @class(['flex flex-col sm:flex-row items-center gap-6 sm:gap-10 rounded-3xl border border-border bg-surface-card p-6 shadow-card transition-all hover:shadow-card-hover', 'sm:flex-row-reverse text-right' => $right]) aria-label="{{ $title }} collection">
    <div class="aspect-[4/5] w-full sm:w-64 lg:w-72 shrink-0 overflow-hidden rounded-2xl border border-border/60 bg-surface">
        <img src="{{ asset('legacy/img/'.$image) }}" alt="{{ $title }}" width="400" height="500" class="h-full w-full object-cover object-center transition-transform duration-500 hover:scale-105" loading="lazy">
    </div>
    <div @class(['flex flex-1 flex-col justify-center space-y-3', 'sm:items-end' => $right, 'sm:items-start' => ! $right])>
        <span class="text-xs font-semibold uppercase tracking-widest text-muted">Phân mục</span>
        <h2 class="text-3xl sm:text-4xl font-serif font-normal text-main">{{ $title }}</h2>
        <h3 class="text-sm font-light leading-relaxed text-muted max-w-sm">{{ $subtitle }}</h3>
        <a href="{{ route('shoes.index', ['gender' => $id === 'kid' ? 'kids' : $id]) }}" class="btn self-start sm:self-auto mt-2">Explore</a>
    </div>
</section>
