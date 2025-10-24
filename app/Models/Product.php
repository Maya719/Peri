<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['plan_id', 'payment_gateway_id','alias', 'product_id', 'price_id'];


    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
    public function gateway()
    {
        return $this->belongsTo(PaymentGateway::class);
    }
}
