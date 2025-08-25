<?php

namespace App\Models;

use App\Facades\Helper;
use App\Models\Traits\HasTeamScopedAttributes;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasDefaultTenant;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Filament\Models\Contracts\HasAvatar;
use Illuminate\Contracts\Auth\MustVerifyEmail;

class User extends Authenticatable implements FilamentUser, HasTenants, HasDefaultTenant, HasAvatar, MustVerifyEmail
{
    use Notifiable, HasRoles, HasTeamScopedAttributes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_super_admin',
        'latest_team_id',
        'subscription_plan_id',
        'father_name',
        'blood_group',
        'date_of_birth',
        'cnic',
        'martial_status',
        'gender',
        'phone_number',
        'emergency_person',
        'emergency_contact',
        'joining_date',
        'probation',
        'designation',
        'address',
        'attendance_config',
        'attendance_type',
        'hours_required',
        'work_days',
        'devices',
        'documents',
        'resigned',
        'active',
        'resign_date',
        'remarks',
        'avatar_url',
        'google_id'
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'documents' => 'array',
        'active' => 'boolean',
        'is_super_admin' => 'boolean',
        'work_days' => 'array',
        'devices' => 'array',
        'joining_date' => 'date',
    ];

    public function getFilamentAvatarUrl(): ?string
    {
        if(empty($this->avatar_url))
        {
            $this->avatar_url = 'profile-images/avatar-male.jpg';
        }
        return asset('storage/'.$this->avatar_url);
    }

    public function scopeVisibleToCurrentUser($query)
    {
        if (Auth::user()->hasRole('Admin')) {
            return $query;
        } elseif (Helper::isAssignUsers()) {
            return $query->whereIn('id', Helper::getAssignUsersIds());
        } else {
            return $query->where('id', Auth::user()->id);
        }
    }

    public function funds()
    {
        return $this->belongsToMany(Fund::class)
            ->withPivot('team_id')
            ->withTimestamps();
    }

    public function fundsForCurrentTeam()
    {
        return $this->belongsToMany(Fund::class)
            ->wherePivot('team_id', Filament::getTenant()->id)
            ->withTimestamps();
    }

    public function bankDetails()
    {
        return $this->hasMany(BankDetail::class);
    }

    public function paymentDetailForTeam($teamId)
    {
        return $this->hasOne(BankDetail::class)->where('team_id', $teamId);
    }

    public function approvalSteps(): HasMany
    {
        return $this->hasMany(\App\Models\ApprovalStep::class)->where('team_id', Filament::getTenant()->id);
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)->withTimestamps();
    }

    public function shiftUsers()
    {
        return $this->hasMany(ShiftUser::class);
    }

    public function assignedShift()
    {
        return $this->hasOne(ShiftUser::class)->where('team_id', Filament::getTenant()->id);
    }

    public function departmentUsers()
    {
        return $this->hasMany(DepartmentUser::class);
    }

    public function assignedDepartment()
    {
        return $this->hasOne(DepartmentUser::class)->where('team_id', Filament::getTenant()->id);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function latestTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'latest_team_id');
    }

    public function getTenants(Panel $panel): Collection
    {
        return $this->teams;
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->teams()->whereKey($tenant->getKey())->exists();
    }

    public function getDefaultTenant(Panel $panel): ?Model
    {
        if ($this->latest_team_id && $this->latestTeam && $this->canAccessTenant($this->latestTeam)) {
            return $this->latestTeam;
        }

        return $this->teams()->latest('pivot_created_at')->first() ?? $this->teams()->first();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function setLatestTeam(Team $team): void
    {
        if ($this->canAccessTenant($team)) {
            $this->update(['latest_team_id' => $team->id]);
        }
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaves()
    {
        return $this->hasMany(Leave::class);
    }
}
