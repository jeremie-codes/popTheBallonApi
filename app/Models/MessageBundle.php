<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageBundle extends Model
{
    protected $fillable = ['title', 'messages', 'price', 'currency', 'description', 'popular', 'active'];

    protected function casts(): array
    {
        return [
            'popular' => 'boolean',
            'active' => 'boolean',
        ];
    }
}
