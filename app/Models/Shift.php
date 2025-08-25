<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'name',
        'short_leave',
        'starting_time',
        'ending_time',
        'break_start',
        'break_end',
        'half_day_check_in',
        'half_day_check_out',
    ];
    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function logs()
    {
        return $this->hasMany(ShiftLog::class);
    }

    protected static function booted()
    {
        static::updated(function ($shift) {
            ShiftLog::create([
                'shift_id'           => $shift->id,
                'team_id'           => $shift->team_id,
                'name'               => $shift->name,
                'short_leave'        => $shift->short_leave,
                'starting_time'      => $shift->starting_time,
                'ending_time'        => $shift->ending_time,
                'break_start'        => $shift->break_start,
                'break_end'          => $shift->break_end,
                'half_day_check_in'  => $shift->half_day_check_in,
                'half_day_check_out' => $shift->half_day_check_out,
            ]);
        });
    }
}
