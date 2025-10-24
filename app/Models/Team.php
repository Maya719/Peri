<?php

namespace App\Models;

use Filament\Models\Contracts\HasAvatar;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravelcm\Subscriptions\Traits\HasPlanSubscriptions;
use Illuminate\Support\Str;

class Team extends Model implements HasAvatar
{
    use HasFactory, HasPlanSubscriptions;

    protected $fillable = ['name', 'logo', 'slug', 'country_id', 'timezone'];

    protected $casts = [
        'country_id' => 'integer',
        'state_id' => 'integer',
        'city_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            $model->slug = Str::slug(strtolower($model->name));
        });
    }
    public function getFilamentAvatarUrl(): ?string
    {
        return url('storage/' . $this->logo);
    }
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function getCurrentTenantLabel(): string
    {
        return 'Active team';
    }
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
    public function roles()
    {
        return $this->hasMany(Role::class);
    }

    public function departments()
    {
        return $this->hasMany(Department::class);
    }
    public function shifts()
    {
        return $this->hasMany(Shift::class);
    }
    public function holidays()
    {
        return $this->hasMany(Holiday::class);
    }
    public function companyDetails()
    {
        return $this->hasMany(CompanyDetail::class);
    }
    public function runSalaries()
    {
        return $this->hasMany(RunSalary::class);
    }
    public function biometrics()
    {
        return $this->hasMany(Biometric::class);
    }
    public function paymentLogs()
    {
        return $this->hasMany(PaymentLog::class);
    }
    public function notices()
    {
        return $this->hasMany(Notice::class);
    }
    public function approvalSteps()
    {
        return $this->hasMany(ApprovalStep::class);
    }
    public function salaryComponentCategories()
    {
        return $this->hasMany(SalaryComponentCategory::class);
    }
    public function leaves()
    {
        return $this->hasMany(Leave::class);
    }
    public function events()
    {
        return $this->hasMany(Event::class);
    }
    public function shiftLogs()
    {
        return $this->hasMany(ShiftLog::class);
    }
    public function leaveLogs()
    {
        return $this->hasMany(LeaveLog::class);
    }
    public function leaveTypes()
    {
        return $this->hasMany(LeaveType::class);
    }
    public function attendancePolicies()
    {
        return $this->hasMany(AttendancePolicy::class);
    }
    public function filteredUsers()
    {
        return $this->users()->visibleToCurrentUser();
    }
    public function devices()
    {
        return $this->hasMany(Device::class);
    }

    public function policies()
    {
        return $this->hasMany(AttendancePolicy::class);
    }

    public function salaryComponents()
    {
        return $this->hasMany(SalaryComponent::class);
    }
    public function payRuns()
    {
        return $this->hasMany(PayRun::class);
    }

    public function payrolls()
    {
        return $this->hasMany(Payroll::class);
    }
    public function OffCyclePayroll()
    {
        return $this->hasMany(OffCyclePayroll::class);
    }
    public function OffCyclePayRun()
    {
        return $this->hasMany(OffCyclePayRun::class);
    }
    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }
    public function todos()
    {
        return $this->hasMany(Todo::class);
    }
    public function shiftUsers()
    {
        return $this->hasMany(ShiftUser::class);
    }
    public function departmentUsers()
    {
        return $this->hasMany(DepartmentUser::class);
    }
    public function funds()
    {
        return $this->hasMany(Fund::class);
    }
    public function loans()
    {
        return $this->hasMany(Loan::class);
    }
    public function bankDetails()
    {
        return $this->hasMany(BankDetail::class);
    }
    public function admins()
    {
        return $this->belongsToMany(User::class, 'admins');
    }
    public function attendance_managers()
    {
        return $this->belongsToMany(User::class, 'attendance_managers');
    }
    public function payment_methods()
    {
        return $this->hasMany(PaymentMethod::class);
    }
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
