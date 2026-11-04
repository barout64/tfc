<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/** A TFC person: one login for the whole TFC system. */
#[Fillable(['name', 'email', 'password', 'is_admin', 'is_active', 'is_support', 'station_id', 'department_id', 'job_title', 'meeting_role', 'protocol_role'])]
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

    /** Daily Report: the kituo (station) this person reports for, if any. */
    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function departmentName(): ?string
    {
        return $this->department?->name;
    }

    /** TFC Meetings: admin, organizer, secretary or member (a portal administrator is its administrator too). */
    public function meetingRole(): string
    {
        return $this->is_admin ? 'admin' : ($this->meeting_role ?: 'member');
    }

    /** TFC Protocol: admin, protocol, head, secretary or employee (a portal administrator is its administrator too). */
    public function protocolRole(): string
    {
        return $this->is_admin ? 'admin' : ($this->protocol_role ?: 'employee');
    }

    /** Daily Report: tied to a station, or an administrator who may report for any station. */
    public function isReporter(): bool
    {
        return $this->is_active && ($this->station_id !== null || $this->is_admin);
    }
}
