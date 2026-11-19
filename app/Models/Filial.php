<?php

namespace App\Models;

use App\Services\FilialAtual;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['nome', 'codigo', 'cnpj', 'cidade', 'uf', 'telefone', 'ativo'])]
class Filial extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'filiais';

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('filiais');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'filial_user')->withTimestamps();
    }

    public function scopeAtivas(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    /** Somente as filiais que o usuário autenticado pode acessar (RN-18). */
    public function scopeAcessiveis(Builder $query): Builder
    {
        $ids = app(FilialAtual::class)->permitidas();

        return $ids === null ? $query : $query->whereKey($ids);
    }

    /**
     * RN-17: há motoristas ou veículos ativos nesta filial?
     * As tabelas `motoristas` e `veiculos` chegam na Fase 1; até lá nunca há vínculos.
     */
    public function temVinculosAtivos(): bool
    {
        foreach (['motoristas', 'veiculos'] as $tabela) {
            if (Schema::hasTable($tabela)
                && DB::table($tabela)->where('filial_id', $this->getKey())->where('ativo', true)->exists()) {
                return true;
            }
        }

        return false;
    }

    public function setCnpjAttribute(?string $value): void
    {
        $this->attributes['cnpj'] = $value === null ? null : (preg_replace('/\D/', '', $value) ?: null);
    }

    public function setUfAttribute(?string $value): void
    {
        $this->attributes['uf'] = $value === null || $value === '' ? null : mb_strtoupper($value);
    }

    public function setCodigoAttribute(string $value): void
    {
        $this->attributes['codigo'] = mb_strtoupper(trim($value));
    }
}
