<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $fillable = [
        'team_id',
        'type',
        'stripe_payment_method_id',
        'stripe_customer_id',
      
    ];
    protected $casts = [
        'is_default' => 'boolean',
    ];
    public function team()
    {
        return $this->belongsTo(Team::class);
    }
}
