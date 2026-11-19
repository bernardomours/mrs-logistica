@props(['title' => null, 'description' => null, 'flush' => false])

<section {{ $attributes->class('rounded-2xl border border-borda bg-white shadow-cartao') }}>
    @if ($title || isset($actions))
        <header class="flex flex-wrap items-start justify-between gap-3 px-5 pt-5 sm:px-6">
            <div>
                @if ($title)
                    <h2 class="text-base font-semibold text-tinta">{{ $title }}</h2>
                @endif
                @if ($description)
                    <p class="mt-1 text-sm text-gray-500">{{ $description }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>
    @endif

    <div @class(['px-5 pb-5 sm:px-6 sm:pb-6', 'pt-4' => $title || isset($actions), 'pt-5 sm:pt-6' => ! $title && ! isset($actions), '!p-0' => $flush])>
        {{ $slot }}
    </div>
</section>
