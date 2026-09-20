<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\AdjustUserBalance;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('adjustBalance')
                ->label('Скорректировать баланс')
                ->schema([
                    Select::make('direction')
                        ->label('Операция')
                        ->options(['credit' => 'Начислить', 'debit' => 'Списать'])
                        ->required(),
                    TextInput::make('amount')
                        ->label('Сумма')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->required()
                        ->suffix('ZARQ'),
                    Textarea::make('reason')
                        ->label('Причина')
                        ->minLength(5)
                        ->maxLength(255)
                        ->required(),
                ])
                ->requiresConfirmation()
                ->authorize('adjustBalance')
                ->action(function (array $data, AdjustUserBalance $adjustUserBalance): void {
                    $administrator = Filament::auth()->user();

                    if (! $administrator instanceof User) {
                        return;
                    }

                    $amount = (int) $data['amount'];
                    $adjustUserBalance->handle(
                        $administrator,
                        $this->getRecord(),
                        $data['direction'] === 'debit' ? -$amount : $amount,
                        $data['reason'],
                    );
                    $this->getRecord()->refresh();

                    Notification::make()->title('Баланс скорректирован')->success()->send();
                }),
            EditAction::make(),
        ];
    }
}
