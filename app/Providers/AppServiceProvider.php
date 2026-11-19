<?php

namespace App\Providers;

use App\Services\FilialAtual;
use App\Services\TravaDeSemana;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Uma instância por requisição/job (Octane-safe).
        $this->app->scoped(FilialAtual::class, fn ($app) => new FilialAtual($app->make('session.store')));
        $this->app->scoped(TravaDeSemana::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Carbon::setLocale(config('app.locale'));

        // Administrador passa pelas checagens de permissão (`can('viagens.ver_valores')`), mas NÃO pelas Policies
        // de model: regras de estado (viagem cancelada, etc.) valem para todos.
        Gate::before(fn ($user, string $ability, array $arguments) => $arguments === [] && $user->hasRole('admin') ? true : null);
    }
}
