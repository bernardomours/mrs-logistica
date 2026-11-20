<?php

namespace App\Livewire\Papeis;

use App\Actions\Papeis\ExcluirPapel;
use App\Livewire\Concerns\EmiteToast;
use App\Support\PapeisDoSistema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;

#[Layout('components.layouts.app')]
#[Title('Papéis')]
class Index extends Component
{
    use EmiteToast;

    public function mount(): void
    {
        Gate::authorize('viewAny', Role::class);
    }

    public function excluir(int $id): void
    {
        $papel = Role::query()->where('guard_name', 'web')->findOrFail($id);

        Gate::authorize('delete', $papel);

        try {
            app(ExcluirPapel::class)->handle(auth()->user(), $papel);
        } catch (ValidationException $e) {
            $this->toast($e->validator->errors()->first(), 'erro');

            return;
        }

        $this->toast('Papel '.PapeisDoSistema::rotulo($papel->name).' excluído.');
    }

    public function render(): View
    {
        $papeis = Role::query()->where('guard_name', 'web')
            ->withCount(['users', 'permissions'])
            ->get()
            ->sortBy(fn (Role $papel) => (PapeisDoSistema::ehDoSistema($papel->name) ? '0' : '1').mb_strtolower(PapeisDoSistema::rotulo($papel->name)))
            ->values();

        return view('livewire.papeis.index', ['papeis' => $papeis]);
    }
}
