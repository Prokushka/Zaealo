<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('admin:revoke {email : Email пользователя}')]
#[Description('Отозвать доступ пользователя к административной панели')]
class RevokeAdminAccess extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->components->error('Пользователь с таким email не найден.');

            return self::FAILURE;
        }

        $user->update(['admin_role' => null]);
        $this->components->info("Доступ пользователя {$user->email} отозван.");

        return self::SUCCESS;
    }
}
