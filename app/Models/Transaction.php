<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'reference',
        'user_id',
        'bundle_id',
        'amount',
        'currency',
        'phone',
        'payment_method',
        'order_number',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bundle()
    {
        return $this->belongsTo(MessageBundle::class, 'bundle_id');
    }
}
