<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/** A TFC person: one login for the whole system, one role governing what they may do everywhere in it. */
#[Fillable(['name', 'email', 'password', 'is_admin', 'is_active', 'phone', 'role', 'warehouse_id', 'agro_dealer_id', 'transporter_id', 'locale'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_active' => 'boolean',
            'role' => Role::class,
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function agroDealer(): BelongsTo
    {
        return $this->belongsTo(AgroDealer::class);
    }

    public function transporter(): BelongsTo
    {
        return $this->belongsTo(Transporter::class);
    }

    /** Meeting and Protocol are still separate deployments until their own phases land - this is how a person's account there is found. Goes away once they're native too. */
    public function links(): HasMany
    {
        return $this->hasMany(SystemLink::class);
    }

    public function linkTo(string $system): ?SystemLink
    {
        return $this->links->firstWhere('system', $system);
    }

    /** Kilimo (and, as later modules land, Meeting/Protocol) ability check. `is_admin` stays a separate, simpler flag for the TFC Portal's own people/access admin screens. */
    public function hasAbility(string $ability): bool
    {
        return $this->is_active && $this->role !== null && $this->role->can($ability);
    }

    /** Warehouse-bound officers only see their own warehouse. */
    public function isScopedToWarehouse(): bool
    {
        return $this->role === Role::WarehouseOfficer && $this->warehouse_id !== null;
    }

    public function initials(): string
    {
        return collect(explode(' ', $this->name))->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('');
    }
}
