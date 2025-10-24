<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankDetail extends Model
{
    protected $fillable = [
        'user_id',
        'team_id',
        'base_salary',
        'payment_method',
        'account_number',
        'account_holder_name',
        'bank_name',
        'salary_currency',
        'statutory_component_category_id',
        'statutory_component_percentage',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }
}
