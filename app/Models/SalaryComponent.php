<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryComponent extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'title',
        'component_type',
        'value_type',
        'amount',
        'tax_status',
        'is_one_time_deduction',
        'is_active',
        'apply_to_all',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'apply_to_all' => 'boolean',
        'show_in_payslip' => 'boolean',
        'is_one_time_deduction' => 'boolean',
        'amount' => 'decimal:2',
    ];


    /**
     * Get the user associated with this specific salary component.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the department associated with this specific salary component.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function category()
    {
        return $this->belongsTo(SalaryComponentCategory::class, 'salary_component_category_id');
    }


    /**
     * Scope a query to only include components for a given admin.
     */
    public function scopeForAdmin($query, $adminId)
    {
        return $query->where('team_id', $adminId);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }
}
