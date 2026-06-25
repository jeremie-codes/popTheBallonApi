<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Carbon;
use Filament\Models\Contracts\HasName;
use Filament\Panel;

#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasName
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'expo_token',
        'first_name',
        'last_name',
        'username',
        'role',
        'phone',
        'email',
        'password',
        'birth_date',
        'gender',
        'city',
        'country',
        'intention',
        'bio',
        'verified',
        'avatar',
        'is_visible',
        'last_seen_at',
    ];

    public function interests()
    {
        return $this->hasMany(UserInterest::class);
    }

    public function devices()
    {
        return $this->hasMany(UserDevice::class);
    }

    public function photos()
    {
        return $this->hasMany(ProfilePhoto::class)->orderBy('position');
    }

    public function stories()
    {
        return $this->hasMany(Story::class);
    }

    public function messageCredit()
    {
        return $this->hasOne(MessageCredit::class);
    }

    public function sentActions()
    {
        return $this->hasMany(ProfileAction::class, 'actor_id');
    }

    public function receivedActions()
    {
        return $this->hasMany(ProfileAction::class, 'target_id');
    }

    public function displayName(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? '')) ?: $this->username ?: 'Utilisateur';
    }

    public function age(): ?int
    {
        return $this->birth_date instanceof Carbon ? $this->birth_date->age : null;
    }

    public function getFilamentName(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'birth_date' => 'date',
            'verified' => 'boolean',
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    } 
}
