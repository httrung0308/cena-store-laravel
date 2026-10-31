@props(['title', 'subtitle', 'image', 'right' => false, 'id'])
<section id="collection-{{ $id }}" @class(['-mb-8 flex h-96 gap-4 sm:gap-8', 'flex-row-reverse self-end' => $right]) aria-label="{{ $title }} collection">
    <div class="h-80 w-48 shrink-0 sm:w-64 xl:h-auto xl:w-80">
        <img src="{{ asset('legacy/img/'.$image) }}" alt="{{ $title }}" width="400" height="500" class="h-full w-full object-cover object-center" loading="lazy">
    </div>
    <div @class(['mt-10 flex flex-col', 'items-end text-right' => $right, 'items-start text-left' => ! $right])>
        <h2 class="-mb-2 text-3xl font-bold sm:text-5xl">{{ $title }}</h2>
        <h3 class="w-2/3 text-sm">{{ $subtitle }}</h3>
        <button type="button" class="btn mt-2 w-max" @click="openNotice('{{ $title }} collection')">Explore</button>
    </div>
</section>
