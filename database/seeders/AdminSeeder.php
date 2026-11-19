<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Cria (ou atualiza) o administrador inicial a partir de ADMIN_NOME, ADMIN_EMAIL e ADMIN_PASSWORD do .env.
 * Nunca grava senha no código. Exige troca de senha no primeiro acesso.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $senha = env('ADMIN_PASSWORD');

        if (blank($email) || blank($senha)) {
            throw new RuntimeException('Defina ADMIN_EMAIL e ADMIN_PASSWORD no .env antes de rodar o AdminSeeder.');
        }

        $admin = User::query()->firstOrNew(['email' => $email]);

        if (! $admin->exists) {
            $admin->password = $senha; // cast "hashed" aplica o hash
            $admin->deve_trocar_senha = true;
        }

        $admin->name = env('ADMIN_NOME', 'Administrador');
        $admin->ativo = true;
        $admin->save();

        $admin->syncRoles(['admin']);
    }
}
