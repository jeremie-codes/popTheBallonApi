<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageCredit extends Model
{
    protected $fillable = [
        'user_id',
        'total_messages',
        'available_messages',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
