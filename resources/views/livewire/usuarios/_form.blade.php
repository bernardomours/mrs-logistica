{{-- Campos compartilhados entre Create e Edit. Espera $this->form (UsuarioForm), $papeis, $filiais, $filiaisEscolhidas, $papelVeTodas e $editando. --}}
{{-- Só quem NÃO é administrador fica travado no próprio papel/situação; administrador pode tudo (a trava do último admin é no servidor). --}}
@php($ehOProprio = $editando && $form->usuario->is(auth()->user()) && ! auth()->user()->hasRole(\App\Support\PapeisDoSistema::ADMIN))

<div class="grid gap-5 sm:grid-cols-2">
    <x-ui.field label="Nome" name="form.name" :required="true">
        <input type="text" wire:model="form.name" maxlength="150" autofocus @class(['campo', 'campo-erro' => $errors->has('form.name')])>
    </x-ui.field>

    <x-ui.field label="E-mail (login)" name="form.email" :required="true">
        <input type="email" wire:model="form.email" maxlength="150" autocomplete="off" @class(['campo', 'campo-erro' => $errors->has('form.email')])>
    </x-ui.field>

    <x-ui.field label="CPF" name="form.cpf" hint="Opcional. Não serve para entrar: o CPF é o login só dos motoristas.">
        <input type="text" wire:model="form.cpf" inputmode="numeric" maxlength="14" @class(['campo', 'campo-erro' => $errors->has('form.cpf')])>
    </x-ui.field>

    <x-ui.field label="Papel" name="form.papel" :required="true"
                :hint="$ehOProprio ? 'Você não pode alterar o seu próprio papel.' : 'O papel define o que a pessoa pode fazer. Gerencie em Papéis.'">
        <select wire:model.live="form.papel" @disabled($ehOProprio) @class(['campo', 'campo-erro' => $errors->has('form.papel')])>
            <option value="">Selecione</option>
            @foreach ($papeis as $opcao)
                <option value="{{ $opcao->name }}">{{ \App\Support\PapeisDoSistema::rotulo($opcao->name) }}</option>
            @endforeach
            @if ($editando && $form->papel !== '' && ! $papeis->contains('name', $form->papel))
                <option value="{{ $form->papel }}">{{ \App\Support\PapeisDoSistema::rotulo($form->papel) }}</option>
            @endif
        </select>
    </x-ui.field>

    <div class="sm:col-span-2">
        <p class="rotulo">Filiais com acesso</p>
        @if ($papelVeTodas)
            <p class="mt-1 rounded-lg bg-roxo-50 px-3 py-2 text-sm text-roxo-700">Este papel enxerga todas as filiais; a seleção abaixo só define a filial padrão.</p>
        @endif
        <div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($filiais as $filial)
                <label wire:key="filial-{{ $filial->id }}" class="flex min-h-11 items-center gap-2 rounded-lg border border-borda px-3 text-sm text-tinta">
                    <input type="checkbox" wire:model.live="form.filiais" value="{{ $filial->id }}" class="size-4 rounded border-borda text-roxo-600 focus:ring-roxo-200">
                    {{ $filial->nome }} <span class="text-xs text-gray-500">({{ $filial->codigo }})</span>
                </label>
            @empty
                <p class="text-sm text-gray-500">Nenhuma filial ativa disponível.</p>
            @endforelse
        </div>
        @error('form.filiais')
            <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <x-ui.field label="Filial padrão" name="form.filial_padrao_id" hint="Opcional. Filial usada nos lançamentos quando nenhuma é escolhida.">
        <select wire:model="form.filial_padrao_id" @class(['campo', 'campo-erro' => $errors->has('form.filial_padrao_id')])>
            <option value="">Nenhuma</option>
            @foreach ($filiaisEscolhidas as $filial)
                <option value="{{ $filial->id }}">{{ $filial->nome }}</option>
            @endforeach
        </select>
    </x-ui.field>

    <x-ui.field :label="$editando ? 'Nova senha' : 'Senha'" name="form.senha" :required="! $editando"
                :hint="$editando ? 'Deixe em branco para manter a senha atual. Mínimo de 8 caracteres, com letras e números.' : 'Mínimo de 8 caracteres, com letras e números.'">
        <x-ui.input-senha wire:model="form.senha" :erro="$errors->has('form.senha')" :gerar="true" autocomplete="new-password" />
    </x-ui.field>

    <div class="flex flex-col justify-end gap-3 pb-1">
        <label class="flex items-center gap-2 text-sm text-tinta">
            <input type="checkbox" wire:model="form.deve_trocar_senha" class="size-4 rounded border-borda text-roxo-600 focus:ring-roxo-200">
            Pedir para trocar a senha no próximo acesso
        </label>

        <label class="flex items-center gap-2 text-sm text-tinta">
            <input type="checkbox" wire:model="form.ativo" @disabled($ehOProprio) class="size-4 rounded border-borda text-roxo-600 focus:ring-roxo-200">
            Usuário ativo
        </label>
        @error('form.ativo')
            <p class="text-xs font-medium text-red-600">{{ $message }}</p>
        @enderror
    </div>
</div>
