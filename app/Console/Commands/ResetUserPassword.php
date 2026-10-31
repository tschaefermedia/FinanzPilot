<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;

class ResetUserPassword extends Command
{
    protected $signature = 'user:reset-password';

    protected $description = 'Set a new login password (recovery when the password is lost)';

    public function handle(): int
    {
        $user = User::query()->first();

        if (! $user) {
            $this->error('Es gibt noch kein Benutzerkonto. Öffne die App im Browser, um es einzurichten.');

            return self::FAILURE;
        }

        $newPassword = password(
            label: "Neues Passwort für {$user->email}",
            required: true,
            validate: fn (string $value) => Validator::make(
                ['password' => $value],
                ['password' => [Password::min(8)]],
            )->errors()->first('password') ?: null,
        );

        $user->update(['password' => $newPassword]);

        $this->info('Passwort wurde geändert.');

        return self::SUCCESS;
    }
}
