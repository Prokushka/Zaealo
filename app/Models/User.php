<?php

namespace App\Models;

use App\Enums\AdminRole;
use App\Notifications\VerifyEmailNotification;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'password', 'balance', 'admin_role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    private const string SUPPORT_CODE_ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin'
            && $this->isAdministrator()
            && $this->hasVerifiedEmail();
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    public function isOwner(): bool
    {
        return $this->admin_role === AdminRole::Owner;
    }

    public function isAdministrator(): bool
    {
        return $this->admin_role !== null;
    }

    /**
     * @return HasMany<SocialAccount, $this>
     */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    /** @return HasMany<Card, $this> */
    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }

    /** @return HasManyThrough<CardGeneration, Card, $this> */
    public function cardGenerations(): HasManyThrough
    {
        return $this->hasManyThrough(CardGeneration::class, Card::class);
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<MarketplaceApiKey, $this> */
    public function marketplaceApiKeys(): HasMany
    {
        return $this->hasMany(MarketplaceApiKey::class);
    }

    /** @return HasMany<AdminBalanceAdjustment, $this> */
    public function balanceAdjustments(): HasMany
    {
        return $this->hasMany(AdminBalanceAdjustment::class);
    }

    /** @return HasMany<AdminBalanceAdjustment, $this> */
    public function performedBalanceAdjustments(): HasMany
    {
        return $this->hasMany(AdminBalanceAdjustment::class, 'administrator_id');
    }

    /** @return HasMany<SupportTicket, $this> */
    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    /** @return HasMany<SupportTicket, $this> */
    public function assignedSupportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'assigned_to_id');
    }

    /** @return HasMany<SupportMessage, $this> */
    public function supportMessages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'author_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'admin_role' => AdminRole::class,
            'balance' => 'integer',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            $user->support_code ??= static::generateSupportCode();
        });

        static::updating(function (User $user): void {
            if ($user->isDirty('support_code')) {
                $user->support_code = $user->getOriginal('support_code');
            }
        });
    }

    private static function generateSupportCode(): string
    {
        do {
            $characters = collect(range(1, 12))
                ->map(fn (): string => self::SUPPORT_CODE_ALPHABET[random_int(0, Str::length(self::SUPPORT_CODE_ALPHABET) - 1)])
                ->implode('');
            $supportCode = 'ZQ-'.implode('-', str_split($characters, 4));
        } while (static::query()->where('support_code', $supportCode)->exists());

        return $supportCode;
    }
}
