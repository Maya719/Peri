<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class Leave extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'user_id',
        'type',
        'paid',
        'leave_type',
        'half_day_timing',
        'starting_date',
        'ending_date',
        'starting_time',
        'ending_time',
        'document',
        'leave_reason',
        'status',
        'rejection_reason',
    ];

    protected $casts = [
        'paid' => 'boolean',
        'starting_date' => 'date',
        'ending_date' => 'date',
        'starting_time' => 'datetime',
        'ending_time' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($leave) {
            $leave->user_id = Auth::id();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function leaveLogs()
    {
        return $this->hasMany(LeaveLog::class);
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }
    public function team()
    {
        return $this->belongsTo(Team::class);
    }
}
