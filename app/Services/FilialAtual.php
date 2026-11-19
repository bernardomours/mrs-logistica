<?php

namespace App\Services;

use App\Models\Filial;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Auth;

/**
 * Resolve quais filiais o usuário autenticado pode acessar e qual está selecionada
 * no seletor de filial (RN-18). Escolher uma filial na sessão NUNCA amplia o acesso.
 */
class FilialAtual
{
    public const SESSAO = 'filial_atual_id';

    /** @var array<int|string, array<int, int>> */
    private array $permitidasEmCache = [];

    public function __construct(private readonly Session $sessao) {}

    public function usuario(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    /**
     * IDs das filiais permitidas ao usuário.
     * `null` = todas (usuário com `filiais.ver_todas`); `[]` = nenhuma.
     *
     * @return array<int, int>|null
     */
    public function permitidas(?User $usuario = null): ?array
    {
        $user = $usuario ?? $this->usuario();

        if ($user === null) {
            return [];
        }

        if ($user->veTodasAsFiliais()) {
            return null;
        }

        // Memoizado por usuário durante a requisição (as listagens consultam a policy linha a linha).
        return $this->permitidasEmCache[$user->getKey()] ??= $user->filiais()->pluck('filiais.id')->map(fn ($id) => (int) $id)->all();
    }

    /** Descarta o cache de filiais permitidas (use depois de alterar `filial_user` na mesma requisição). */
    public function esquecer(): void
    {
        $this->permitidasEmCache = [];
    }

    /** O usuário (por padrão o autenticado) pode acessar esta filial? Actions passam o usuário explicitamente. */
    public function podeAcessar(int $filialId, ?User $usuario = null): bool
    {
        $permitidas = $this->permitidas($usuario);

        return $permitidas === null || in_array($filialId, $permitidas, true);
    }

    /** Filial escolhida no seletor, ou `null` para "Todas". */
    public function selecionada(): ?int
    {
        $id = $this->sessao->get(self::SESSAO);

        if ($id === null || $id === 'todas') {
            return null;
        }

        $id = (int) $id;
        $permitidas = $this->permitidas();

        return ($permitidas === null || in_array($id, $permitidas, true)) ? $id : null;
    }

    /**
     * Filiais efetivamente consultadas (interseção entre permitidas e selecionada).
     * `null` = sem restrição; `[]` = nenhuma.
     *
     * @return array<int, int>|null
     */
    public function idsParaConsulta(): ?array
    {
        $permitidas = $this->permitidas();
        $selecionada = $this->selecionada();

        if ($selecionada !== null) {
            return [$selecionada];
        }

        return $permitidas;
    }

    /** Define a filial ativa; `null` = "Todas". Ignora filiais não permitidas. */
    public function selecionar(?int $filialId): void
    {
        if ($filialId === null) {
            $this->sessao->put(self::SESSAO, 'todas');

            return;
        }

        $permitidas = $this->permitidas();

        if ($permitidas === null || in_array($filialId, $permitidas, true)) {
            $this->sessao->put(self::SESSAO, $filialId);
        }
    }

    /** Filial a gravar em novos lançamentos quando o usuário não escolheu uma explicitamente. */
    public function paraNovoLancamento(): ?int
    {
        $selecionada = $this->selecionada();

        if ($selecionada !== null) {
            return $selecionada;
        }

        $user = $this->usuario();

        if ($user?->filial_padrao_id !== null) {
            return (int) $user->filial_padrao_id;
        }

        $permitidas = $this->permitidas();

        return is_array($permitidas) && count($permitidas) === 1 ? $permitidas[0] : null;
    }

    /** Filiais para o seletor da interface. */
    public function disponiveis()
    {
        return Filial::query()->ativas()->acessiveis()->orderBy('nome')->get(['id', 'nome', 'codigo']);
    }
}
