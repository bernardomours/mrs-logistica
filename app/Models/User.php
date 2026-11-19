<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'cpf', 'password', 'ativo', 'deve_trocar_senha', 'filial_padrao_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'ativo' => 'boolean',
            'deve_trocar_senha' => 'boolean',
            'ultimo_login_at' => 'datetime',
        ];
    }

    /** Filiais que o usuário pode acessar (RN-18). */
    public function filiais(): BelongsToMany
    {
        return $this->belongsToMany(Filial::class, 'filial_user')->withTimestamps();
    }

    public function filialPadrao(): BelongsTo
    {
        return $this->belongsTo(Filial::class, 'filial_padrao_id');
    }

    /** Usuário com visão consolidada de todas as filiais. */
    public function veTodasAsFiliais(): bool
    {
        return $this->can('filiais.ver_todas');
    }
}
