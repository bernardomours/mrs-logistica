@props(['eyebrow' => null, 'title', 'subtitle' => null])

<div {{ $attributes->class('mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between') }}>
    <div class="min-w-0">
        @if ($eyebrow)
            <p class="mb-2 text-[11px] font-semibold tracking-[0.18em] text-gray-500 uppercase">{{ $eyebrow }}</p>
        @endif
        <h1 class="text-2xl font-bold tracking-tight text-tinta sm:text-3xl">
            {{ $title }}<span class="ml-2 inline-block size-1.5 rounded-full bg-roxo-500 align-middle"></span>
        </h1>
        @if ($subtitle)
            <p class="mt-1.5 text-sm text-gray-500">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
