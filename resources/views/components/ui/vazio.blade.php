@props(['icone' => 'info', 'titulo' => 'Nenhum registro encontrado', 'filtrado' => false, 'dica' => 'Cadastre o primeiro registro para começar.'])

<div class="px-6 py-14 text-center">
    <x-ui.icon :name="$icone" class="mx-auto size-10 text-gray-300" />
    <p class="mt-3 text-sm font-medium text-tinta">{{ $titulo }}</p>
    <p class="mt-1 text-sm text-gray-500">{{ $filtrado ? 'Ajuste ou limpe os filtros para ver mais resultados.' : $dica }}</p>
</div>
