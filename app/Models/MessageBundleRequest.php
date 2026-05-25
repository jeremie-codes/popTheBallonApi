<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageBundleRequest extends Model
{
    protected $fillable = ['requester_id', 'requested_user_id', 'status'];
}
