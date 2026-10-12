<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'google_id',
        'password',
        'role',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_changed_at' => 'datetime',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function isStudent(): bool
    {
        return $this->role === UserRole::Student;
    }

    /**
     * An OSDW staff account still on its generated password, or (when
     * Google sign-in is configured) not yet tied to a Google account.
     */
    public function needsAccountSetup(): bool
    {
        if ($this->role !== UserRole::OsdwStaff) {
            return false;
        }

        return $this->password_changed_at === null
            || ($this->google_id === null && filled(config('services.google.client_id')));
    }

    public function scopeStudents(Builder $query): Builder
    {
        return $query->where('role', UserRole::Student->value)->where('is_active', true);
    }

    public function scopeStaff(Builder $query): Builder
    {
        return $query->whereIn('role', array_map(fn (UserRole $role) => $role->value, UserRole::staffRoles()));
    }

    /**
     * Where this user lands after signing in.
     */
    public function homeRoute(): string
    {
        if ($this->isStudent()) {
            return route('student.applications.index');
        }

        return $this->needsAccountSetup() ? route('staff.account.setup') : route('dashboard');
    }

    /**
     * A student's applicant record — the student ID and personal details
     * every application they submit is filed under.
     */
    public function applicant(): HasOne
    {
        return $this->hasOne(Applicant::class);
    }

    public function verifiedDocuments(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class, 'verified_by');
    }

    public function statusChanges(): HasMany
    {
        return $this->hasMany(ApplicationStatusLog::class, 'changed_by');
    }

    public function awardsGiven(): HasMany
    {
        return $this->hasMany(Award::class, 'awarded_by');
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class, 'created_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}
