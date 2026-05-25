<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfileAction extends Model
{
    protected $fillable = ['actor_id', 'target_id', 'type'];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function target()
    {
        return $this->belongsTo(User::class, 'target_id');
    }
}
