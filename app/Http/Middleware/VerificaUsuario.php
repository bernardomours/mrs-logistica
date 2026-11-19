<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Roda a cada requisição autenticada:
 *  - desloga usuário desativado (RN-17);
 *  - obriga a troca de senha provisória antes de qualquer outra tela (RN-21).
 */
class VerificaUsuario
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario !== null && ! $usuario->ativo) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['login' => 'Seu acesso está desativado. Fale com o administrador.']);
        }

        if ($usuario?->deve_trocar_senha && ! $request->routeIs('senha.trocar')) {
            return redirect()->route('senha.trocar');
        }

        return $next($request);
    }
}
