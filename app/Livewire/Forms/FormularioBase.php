<?php

namespace App\Livewire\Forms;

use Illuminate\Validation\ValidationException;
use Livewire\Form;

/**
 * Base dos Form Objects de cadastro. Erros de regra de negócio lançados pelas Actions
 * (`ValidationException` com chave "campo") passam a apontar para "form.campo" na tela.
 */
abstract class FormularioBase extends Form
{
    /**
     * @template T
     *
     * @param  callable(): T  $acao
     * @return T
     */
    protected function executar(callable $acao): mixed
    {
        try {
            return $acao();
        } catch (ValidationException $e) {
            $prefixados = [];

            foreach ($e->errors() as $campo => $mensagens) {
                $prefixados[str_starts_with($campo, 'form.') ? $campo : 'form.'.$campo] = $mensagens;
            }

            throw ValidationException::withMessages($prefixados);
        }
    }
}
