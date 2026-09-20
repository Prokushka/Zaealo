<?php

namespace App\Console\Commands;

use App\Enums\AdminRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('admin:grant {email : Email пользователя} {--role=owner : owner или support}')]
#[Description('Выдать пользователю доступ к административной панели')]
class GrantAdminAccess extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $role = AdminRole::tryFrom((string) $this->option('role'));

        if ($role === null) {
            $this->components->error('Роль должна быть owner или support.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->components->error('Пользователь с таким email не найден.');

            return self::FAILURE;
        }

        $user->update(['admin_role' => $role]);
        $this->components->info("Пользователю {$user->email} назначена роль {$role->value}.");

        return self::SUCCESS;
    }
}
