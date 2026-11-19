{{-- Avisos rápidos. Recebe o evento Livewire "toast" e o flash de sessão "toast". --}}
@php($flash = session('toast'))

<div
    x-data="{
        itens: [],
        adicionar(mensagem, tipo = 'sucesso') {
            const id = Date.now() + Math.random();
            this.itens.push({ id, mensagem, tipo });
            setTimeout(() => this.remover(id), 5000);
        },
        remover(id) { this.itens = this.itens.filter((i) => i.id !== id); },
    }"
    x-init="@if ($flash) adicionar(@js($flash['mensagem'] ?? ''), @js($flash['tipo'] ?? 'sucesso')) @endif"
    x-on:toast.window="adicionar($event.detail.mensagem, $event.detail.tipo)"
    class="pointer-events-none fixed inset-x-0 bottom-0 z-50 flex flex-col items-center gap-2 p-4 sm:items-end sm:p-6"
    aria-live="polite"
>
    <template x-for="item in itens" :key="item.id">
        <div
            x-transition.opacity
            class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border bg-white px-4 py-3 text-sm shadow-lg"
            :class="{
                'border-emerald-200': item.tipo === 'sucesso',
                'border-red-200': item.tipo === 'erro',
                'border-amber-200': item.tipo === 'aviso',
            }"
            role="status"
        >
            <span class="mt-1 size-2 shrink-0 rounded-full"
                :class="{
                    'bg-emerald-500': item.tipo === 'sucesso',
                    'bg-red-500': item.tipo === 'erro',
                    'bg-amber-500': item.tipo === 'aviso',
                }"></span>
            <p class="flex-1 text-tinta" x-text="item.mensagem"></p>
            <button type="button" class="text-gray-400 hover:text-gray-600" x-on:click="remover(item.id)" aria-label="Fechar">
                <x-ui.icon name="x" class="size-4" />
            </button>
        </div>
    </template>
</div>
