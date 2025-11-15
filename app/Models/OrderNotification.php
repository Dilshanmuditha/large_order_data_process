<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'customer_id',
        'status',
        'total_cents',
        'message',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
    
    public function customer()
    {
        return $this->belongsTo(customer::class);
    }
}
