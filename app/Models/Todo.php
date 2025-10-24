<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Filament\Facades\Filament;

class Todo extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'team_id',
        'task',
        'is_completed',
        'description',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($todo) {
            $todo->user_id = Auth::id();
            $todo->team_id = Filament::getTenant()->id;
        });
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }
}
