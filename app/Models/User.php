<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/** A TFC person: one login for the portal; the systems they may open are their links. */
#[Fillable(['name', 'email', 'password', 'is_admin', 'is_active', 'is_support'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_active' => 'boolean',
            'is_support' => 'boolean',
        ];
    }

    public function links(): HasMany
    {
        return $this->hasMany(SystemLink::class);
    }

    public function linkTo(string $system): ?SystemLink
    {
        return $this->links->firstWhere('system', $system);
    }

    /** Uwajibikaji: the deadlines this person missed. */
    public function dutyWarnings(): HasMany
    {
        return $this->hasMany(DutyWarning::class);
    }

    /** Helpdesk support: sees every request and fixes them (administrators too). */
    public function isSupport(): bool
    {
        return $this->is_active && ($this->is_support || $this->is_admin);
    }
}
