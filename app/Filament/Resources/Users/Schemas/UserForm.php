<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\AdminRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Профиль')
                    ->schema([
                        TextInput::make('support_code')
                            ->label('Код клиента')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('name')
                            ->label('Имя')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Select::make('admin_role')
                            ->label('Роль в админке')
                            ->options(collect(AdminRole::cases())->mapWithKeys(
                                fn (AdminRole $role): array => [$role->value => $role->label()],
                            )->all())
                            ->placeholder('Нет доступа'),
                    ])
                    ->columns(2),
            ]);
    }
}
